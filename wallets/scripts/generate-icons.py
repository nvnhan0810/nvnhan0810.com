"""Regenerate the PWA icons in public/icons/ (requires Pillow).

    python3 scripts/generate-icons.py

The artwork mirrors public/icons/icon.svg. Everything sits inside the
maskable safe zone (a circle of radius 0.4 × size), so the same drawing is
used for the rounded "any" icons and the full-bleed maskable ones.
"""

from pathlib import Path

from PIL import Image, ImageDraw

OUT = Path(__file__).resolve().parent.parent / "public" / "icons"
TOP = (79, 70, 229)  # indigo-600
BOTTOM = (124, 58, 237)  # violet-600
SS = 4  # supersampling factor for smooth edges


def gradient(size: int) -> Image.Image:
    img = Image.new("RGB", (size, size))
    px = img.load()
    for y in range(size):
        for x in range(size):
            t = (x + y) / (2 * (size - 1))
            px[x, y] = tuple(round(a + (b - a) * t) for a, b in zip(TOP, BOTTOM))
    return img


def draw(size: int, rounded: bool) -> Image.Image:
    big = size * SS
    k = big / 512
    bg = gradient(big).convert("RGBA")
    art = Image.new("RGBA", (big, big), (0, 0, 0, 0))
    d = ImageDraw.Draw(art)

    def box(x0, y0, x1, y1):
        return [round(x0 * k), round(y0 * k), round(x1 * k), round(y1 * k)]

    # Card peeking out of the wallet.
    d.rounded_rectangle(box(150, 120, 350, 200), radius=24 * k, fill=(255, 255, 255, 150))
    # Wallet body.
    d.rounded_rectangle(box(112, 160, 400, 372), radius=44 * k, fill=(255, 255, 255, 255))
    # Clasp.
    d.rounded_rectangle(box(296, 228, 420, 304), radius=28 * k, fill=(199, 210, 254, 255))
    d.ellipse(box(332, 250, 364, 282), fill=TOP + (255,))

    img = Image.alpha_composite(bg, art)
    if rounded:
        mask = Image.new("L", (big, big), 0)
        ImageDraw.Draw(mask).rounded_rectangle([0, 0, big - 1, big - 1], radius=112 * k, fill=255)
        img.putalpha(mask)
    return img.resize((size, size), Image.LANCZOS)


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    for size in (192, 512):
        draw(size, rounded=True).save(OUT / f"icon-{size}.png", optimize=True)
        draw(size, rounded=False).save(OUT / f"icon-maskable-{size}.png", optimize=True)
    # iOS applies its own corner mask and ignores transparency.
    draw(180, rounded=False).convert("RGB").save(OUT / "apple-touch-icon.png", optimize=True)
    draw(32, rounded=True).save(OUT / "favicon-32.png", optimize=True)


if __name__ == "__main__":
    main()
