"""Genera los iconos de la app (PWA y Android) a partir de un dibujo sencillo."""
from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
MAR = (20, 49, 63, 255)
BLANCO = (255, 255, 255, 255)
PIMENTON = (179, 54, 43, 255)


def motif(size, scale=1.0, bg=None, radius=0.0):
    """Plato con tenedor y cuchillo. scale = tamaño del dibujo respecto al lienzo."""
    S = size * 4  # dibujar grande y reducir = bordes suaves
    img = Image.new('RGBA', (S, S), bg or (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if bg and radius:
        img = Image.new('RGBA', (S, S), (0, 0, 0, 0))
        d = ImageDraw.Draw(img)
        d.rounded_rectangle([0, 0, S - 1, S - 1], radius=int(S * radius), fill=bg)
    c = S / 2
    u = S * scale / 100  # unidad: 1/100 del área del dibujo
    # plato
    r1, r2 = 30 * u, 22 * u
    d.ellipse([c - r1, c - r1, c + r1, c + r1], outline=BLANCO, width=int(4.5 * u))
    d.ellipse([c - r2, c - r2, c + r2, c + r2], fill=PIMENTON)
    # tenedor (izquierda)
    fx = c - 42 * u
    top, bot = c - 30 * u, c + 34 * u
    w = 2.6 * u
    for dx in (-5, 0, 5):
        d.rounded_rectangle([fx + dx * u - w / 2, top, fx + dx * u + w / 2, top + 16 * u], radius=w / 2, fill=BLANCO)
    d.rounded_rectangle([fx - 7.6 * u, top + 13 * u, fx + 7.6 * u, top + 20 * u], radius=3 * u, fill=BLANCO)
    d.rounded_rectangle([fx - 2.4 * u, top + 18 * u, fx + 2.4 * u, bot], radius=2.4 * u, fill=BLANCO)
    # cuchillo (derecha)
    kx = c + 42 * u
    d.polygon([(kx - 3 * u, top), (kx + 4.5 * u, top + 6 * u), (kx + 4.5 * u, top + 30 * u), (kx - 3 * u, top + 30 * u)], fill=BLANCO)
    d.rounded_rectangle([kx - 3 * u, top + 28 * u, kx + 2.6 * u, bot], radius=2.6 * u, fill=BLANCO)
    return img.resize((size, size), Image.LANCZOS)


def save(img, path, rgb=False):
    path.parent.mkdir(parents=True, exist_ok=True)
    if rgb:
        bg = Image.new('RGB', img.size, MAR[:3])
        bg.paste(img, mask=img.split()[3])
        img = bg
    img.save(path)
    print('ok', path.relative_to(ROOT))


icons = ROOT / 'app' / 'www' / 'icons'
save(motif(192, 0.78, MAR, 0.22), icons / 'icon-192.png')
save(motif(512, 0.78, MAR, 0.22), icons / 'icon-512.png')
save(motif(512, 0.62, MAR), icons / 'icon-maskable-512.png')
save(motif(180, 0.74, MAR), icons / 'apple-touch-icon.png', rgb=True)

res = ROOT / 'app' / 'assets'
save(motif(1024, 0.74, MAR), res / 'icon-only.png', rgb=True)
save(motif(1024, 0.50), res / 'icon-foreground.png')
save(Image.new('RGBA', (1024, 1024), MAR), res / 'icon-background.png')
splash = Image.new('RGBA', (2732, 2732), MAR)
splash.alpha_composite(motif(700, 0.8), ((2732 - 700) // 2, (2732 - 700) // 2))
save(splash, res / 'splash.png', rgb=True)
save(splash, res / 'splash-dark.png', rgb=True)
