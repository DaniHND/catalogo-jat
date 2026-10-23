"""Extrae los productos del catálogo PDF de JAT y genera:
  - uploads/<codigo>.png  (recorte de la imagen de cada producto)
  - sql/productos_catalogo.sql  (INSERTs para la tabla products)

Uso:  python -I scripts/extract_catalog.py "CATALOGO JAT 2023-13Mb 230623.pdf"
"""
import json
import os
import re
import sys

import pymupdf as fitz

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UPLOADS = os.path.join(ROOT, "uploads")
SQL_OUT = os.path.join(ROOT, "sql", "productos_catalogo.sql")

# Categoría según página del PDF (1-based), tomada del índice del catálogo
CATEGORIES = [
    (4, 19, "Pollo de engorda"),
    (20, 29, "Gallina ponedora"),
    (30, 33, "Procesamiento de pollo"),
    (34, 34, "Lechón / Cerdo"),
    (35, 35, "Gallo de combate"),
    (36, 36, "Conejo"),
    (37, 37, "Diversas especies"),
]

# Productos que vienen en tablas de especificaciones: el nombre sale del prefijo del código
TABLE_TITLES = {
    "INCU": "Incubadora automática digital",
    "NACE": "Nacedora digital",
    "MDPX": "Mesa de desangrado",
    "ESCX": "Escaldadora de aspas",
    "PELX": "Desplumadora de olla",
}
TABLE_EN = {"Commercial", "Semi-Industrial", "Layers", "Quail", "Eggs"}

CODE_RE = re.compile(r"^([A-Z][A-Z0-9]{3}\d{7,8}|DPR\d|BTGX)\b\s*(.*)$")
FIXES = {"Ɵ": "ti", "ﬂ": "fl", "ﬁ": "fi", "ﬀ": "ff", " ": " "}


def clean(s):
    for a, b in FIXES.items():
        s = s.replace(a, b)
    return re.sub(r"\s+", " ", s).strip()


def join_lines(parts):
    return clean(" ".join(parts))


def category(pno):
    for a, b, name in CATEGORIES:
        if a <= pno <= b:
            return name
    return ""


def image_clusters(page):
    """Bloques de imagen agrupados cuando se superponen (fotos compuestas de varias piezas)."""
    rects = []
    for b in page.get_text("dict")["blocks"]:
        if b["type"] != 1:
            continue
        r = fitz.Rect(b["bbox"]) & page.rect
        if r.width < 10 or r.height < 10 or r.width > page.rect.width * 0.9:
            continue
        rects.append(r)
    merged = True
    while merged:
        merged = False
        for i in range(len(rects)):
            for j in range(i + 1, len(rects)):
                if (rects[i] + (-2, -2, 2, 2)).intersects(rects[j]):
                    rects[i] = rects[i] | rects[j]
                    del rects[j]
                    merged = True
                    break
            if merged:
                break
    return rects


def pick_image(text_rect, images):
    best, best_score = None, None
    cy = (text_rect.y0 + text_rect.y1) / 2
    for r in images:
        icy = (r.y0 + r.y1) / 2
        dy = abs(icy - cy)
        dx = text_rect.x0 - r.x1  # >0 si la imagen está a la izquierda
        if -10 <= dx <= 160 and dy <= max(45, r.height / 2 + 10):
            score = dy * 1.5 + max(dx, 0)
        else:
            # respaldo: la imagen más cercana en cualquier dirección
            ex = max(r.x0 - text_rect.x1, text_rect.x0 - r.x1, 0)
            ey = max(r.y0 - text_rect.y1, text_rect.y0 - r.y1, 0)
            dist = (ex ** 2 + ey ** 2) ** 0.5
            if dist > 90:
                continue
            score = 300 + dist
        if best_score is None or score < best_score:
            best, best_score = r, score
    return best


def page_spans(page):
    """Todos los spans de la página con su bloque, para reconstruir filas de tablas."""
    out = []
    for b in page.get_text("dict")["blocks"]:
        if b["type"] != 0:
            continue
        paragraph = len(b["lines"]) >= 3 and b["lines"][0]["spans"][0]["size"] >= 9.5
        for ln in b["lines"]:
            for s in ln["spans"]:
                t = clean(s["text"])
                if t:
                    out.append({"text": t, "rect": fitz.Rect(s["bbox"]), "size": s["size"],
                                "font": s["font"], "paragraph": paragraph,
                                "italic": "Italic" in s["font"] or bool(s["flags"] & 2)})
    return out


def table_title(spans, code_rect):
    """Encabezado más cercano por encima del código (títulos de tabla en negrita)."""
    best = None
    for s in spans:
        r = s["rect"]
        if r.y1 > code_rect.y0 or code_rect.y0 - r.y1 > 420 or s["size"] < 9.4:
            continue
        if not ("Bold" in s["font"] or s["font"].endswith("-")) or re.search(r"(\S ){4}", s["text"]):
            continue
        if CODE_RE.match(s["text"]) or len(s["text"]) < 4:
            continue
        if best is None or r.y1 > best["rect"].y1:
            best = s
    return best["text"].split("/")[0].strip(" .") if best else ""


def table_row(spans, code_rect, code):
    cy = (code_rect.y0 + code_rect.y1) / 2
    cells = [s for s in spans
             if abs((s["rect"].y0 + s["rect"].y1) / 2 - cy) < 5
             and s["rect"].x0 > code_rect.x1 - 2 and s["rect"].x0 - code_rect.x1 < 480
             and not s["paragraph"] and not CODE_RE.match(s["text"])]
    words = []
    for s in sorted(cells, key=lambda s: s["rect"].x0):
        t = s["text"].strip()
        # quitar la traducción ("/ Universal", "/Eggs"), "*opcional" y duplicados en inglés
        if t.startswith("/") or t.startswith("*") or t in TABLE_EN or t in words:
            continue
        words.append(t)
    return ", ".join(words)


def text_below(spans, code_rect):
    """Respaldo: líneas justo debajo del código, hasta el siguiente código."""
    below = sorted((s for s in spans
                    if code_rect.x0 - 15 <= s["rect"].x0 <= code_rect.x0 + 60
                    and code_rect.y0 + 2 < s["rect"].y0 <= code_rect.y1 + 60),
                   key=lambda s: (round(s["rect"].y0), s["rect"].x0))
    es, en = [], []
    for s in below:
        if CODE_RE.match(s["text"]):
            if es or en:
                break
            continue
        (en if s["italic"] else es).append(s["text"])
    return join_lines(es), join_lines(en)


def image_above(images, code_rect):
    cands = [r for r in images if r.y1 <= code_rect.y0 + 5 and code_rect.y0 - r.y1 < 400
             and r.x1 > code_rect.x0 - 20 and r.x0 < code_rect.x0 + 400]
    return max(cands, key=lambda r: r.y1) if cands else None


def without_text(page):
    """Borra el texto de la página para que los recortes de imagen salgan limpios."""
    for b in page.get_text("dict")["blocks"]:
        if b["type"] == 0:
            page.add_redact_annot(b["bbox"], fill=False)
    page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_NONE,
                          graphics=fitz.PDF_REDACT_LINE_ART_NONE,
                          text=fitz.PDF_REDACT_TEXT_REMOVE)
    return page


def main(pdf_path):
    doc = fitz.open(pdf_path)
    clean_doc = fitz.open(pdf_path)
    os.makedirs(UPLOADS, exist_ok=True)
    products = {}

    for pno in range(1, len(doc) + 1):
        cat = category(pno)
        if not cat:
            continue
        page = doc[pno - 1]
        images = image_clusters(page)
        clean_page = None
        found = []

        for b in page.get_text("dict")["blocks"]:
            if b["type"] == 1:
                continue
            current = None
            for ln in b["lines"]:
                spans = ln["spans"]
                text = "".join(s["text"] for s in spans)
                first_bold = spans and "Bold" in spans[0]["font"] and spans[0]["size"] < 10
                m = CODE_RE.match(text.strip())
                # celda de tabla: el código aparece solo, en fuente normal
                table = bool(m) and not first_bold and m.group(1) == text.strip()
                if m and not (first_bold or table):
                    m = None
                if m:
                    current = {"code": m.group(1), "es": [], "en": [], "rect": fitz.Rect(ln["bbox"]),
                               "code_rect": fitz.Rect(ln["bbox"]), "table": table}
                    found.append(current)
                    rest = m.group(2)
                    if rest:
                        current["es"].append(rest)
                    continue
                if current is None or current["table"]:
                    continue
                current["rect"] |= fitz.Rect(ln["bbox"])
                es = "".join(s["text"] for s in spans if not ("Italic" in s["font"] or s["flags"] & 2))
                en = "".join(s["text"] for s in spans if "Italic" in s["font"] or s["flags"] & 2)
                current["es"].append(es)
                current["en"].append(en)

        # códigos seguidos sin texto propio comparten la descripción del grupo (BRMT0303018/19/20)
        for i in range(len(found) - 2, -1, -1):
            p, nxt = found[i], found[i + 1]
            if not p["table"] and not "".join(p["es"] + p["en"]).strip() and abs(nxt["code_rect"].y0 - p["code_rect"].y1) < 3:
                p["es"], p["en"], p["rect"] = nxt["es"], nxt["en"], p["rect"] | nxt["rect"]

        spans = None
        for p in found:
            code = p["code"]
            name = join_lines(p["es"]) or join_lines(p["en"])
            en = join_lines(p["en"])
            if not re.search(r"[^\W\d_]{3}", name):  # solo números sueltos de una tabla
                name = en = ""
            img_rect = None
            if not name:
                spans = spans or page_spans(page)
                p["rect"] = p["code_rect"]
                title = TABLE_TITLES.get(code[:4])
                if not title:
                    name, en = text_below(spans, p["code_rect"])
                    name = name or en
                if not name and (title or p["table"]):
                    row = table_row(spans, p["code_rect"], code)
                    title = title or table_title(spans, p["code_rect"])
                    name = f"{title} ({row})" if row else title
                    en = ""
                    img_rect = image_above(images, p["code_rect"])
            if not name or code in products:
                if not name:
                    print("SIN DESCRIPCION:", pno, code, file=sys.stderr)
                continue
            img_rect = img_rect or pick_image(p["rect"], images)
            image = None
            if img_rect is not None:
                image = f"{code}.png"
                clean_page = clean_page or without_text(clean_doc[pno - 1])
                pix = clean_page.get_pixmap(clip=img_rect + (-2, -2, 2, 2), dpi=200)
                pix.save(os.path.join(UPLOADS, image))
            products[code] = {
                "code": code,
                "name": name[:200],
                "type": cat,
                "description": name + (f"\n{en}" if en else ""),
                "image": image,
                "page": pno,
            }

    def q(v):
        if v is None:
            return "NULL"
        return "'" + v.replace("\\", "\\\\").replace("'", "''") + "'"

    with open(SQL_OUT, "w", encoding="utf-8") as f:
        f.write("-- Generado por scripts/extract_catalog.py\nSET NAMES utf8mb4;\nDELETE FROM products;\n")
        for p in products.values():
            f.write(
                "INSERT INTO products (code, name, type, description, image) VALUES ("
                + ", ".join(q(p[k]) for k in ("code", "name", "type", "description", "image"))
                + ");\n"
            )

    with_img = sum(1 for p in products.values() if p["image"])
    print(json.dumps({"productos": len(products), "con_imagen": with_img}, ensure_ascii=False))


if __name__ == "__main__":
    main(sys.argv[1])
