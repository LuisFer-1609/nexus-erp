"""
Genera los recursos de la mascota para la app a partir del modelo de Blender.

Uso:   python3 branding/nexus-3d/build_assets.py [muestras]

1. Renderiza las capas (cabeza sin ojos, cuerpo y avatar) con nexus_robot.py en modo sprites.
2. Las recorta y las convierte a WebP con transparencia en public/images/nexus/.
3. Escribe resources/js/lib/nexusMascotLayout.ts con las posiciones de los ojos y del visor.

Requiere: blender e imagemagick (magick).
"""

import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
HERE = ROOT / "branding" / "nexus-3d"
SPRITES = HERE / "sprites"
OUT = ROOT / "public" / "images" / "nexus"
LAYOUT_TS = ROOT / "resources" / "js" / "lib" / "nexusMascotLayout.ts"
FULL_SIZE = 384  # lado mayor del cuerpo completo, en px


def run(*cmd: str) -> str:
    return subprocess.run(cmd, check=True, capture_output=True, text=True).stdout


def bbox(png: Path) -> tuple[int, int, int, int]:
    """(x, y, ancho, alto) del contenido no transparente."""
    out = run("magick", str(png), "-format", "%@", "info:").strip()
    w, h, x, y = map(int, re.match(r"(\d+)x(\d+)\+(\d+)\+(\d+)", out).groups())
    return x, y, w, h


def main() -> None:
    samples = sys.argv[1] if len(sys.argv) > 1 else "128"
    run(
        "blender", "-b", "--factory-startup",
        "--python", str(HERE / "nexus_robot.py"),
        "--", samples, "sprites",
    )

    layout = json.loads((SPRITES / "layout.json").read_text())
    hx, hy, hw, hh = bbox(SPRITES / "head.png")
    bx, by, bw, bh = bbox(SPRITES / "body.png")

    # Lienzo común = unión de cabeza y cuerpo.
    ux, uy = min(hx, bx), min(hy, by)
    uw = max(hx + hw, bx + bw) - ux
    uh = max(hy + hh, by + bh) - uy
    scale = FULL_SIZE / max(uw, uh)

    OUT.mkdir(parents=True, exist_ok=True)
    for old in OUT.glob("*.webp"):
        old.unlink()

    def export(name: str, src: str, box: tuple[int, int, int, int]) -> tuple[int, int]:
        x, y, w, h = box
        size = (round(w * scale), round(h * scale))
        run(
            "magick", str(SPRITES / f"{src}.png"),
            "-crop", f"{w}x{h}+{x}+{y}", "+repage",
            "-resize", f"{size[0]}x{size[1]}!",
            "-quality", "86",
            "-define", "webp:method=6", "-define", "webp:alpha-quality=95",
            str(OUT / f"{name}.webp"),
        )
        return size

    head_size = export("nexus-head", "head", (hx, hy, hw, hh))
    export("nexus-avatar", "avatar", (hx, hy, hw, hh))
    body_size = export("nexus-body", "body", (bx, by, bw, bh))

    def to_head(point: list[float]) -> list[float]:
        return [round((point[0] - hx) * scale, 2), round((point[1] - hy) * scale, 2)]

    canvas = (round(uw * scale), round(uh * scale))
    data = {
        "canvas": {"width": canvas[0], "height": canvas[1]},
        "head": {
            "width": head_size[0],
            "height": head_size[1],
            "left": round((hx - ux) / uw * 100, 3),
            "top": round((hy - uy) / uh * 100, 3),
            "w": round(hw / uw * 100, 3),
            "h": round(hh / uh * 100, 3),
        },
        "body": {
            "width": body_size[0],
            "height": body_size[1],
            "left": round((bx - ux) / uw * 100, 3),
            "top": round((by - uy) / uh * 100, 3),
            "w": round(bw / uw * 100, 3),
            "h": round(bh / uh * 100, 3),
        },
        "eyeLeft": to_head(layout["eyeLeft"]),
        "eyeRight": to_head(layout["eyeRight"]),
        "unit": round(layout["unit"] * scale, 2),
        "visor": to_head(layout["visor"]),
        "visorRadius": [round(r * scale, 2) for r in layout["visorRadius"]],
    }

    LAYOUT_TS.write_text(
        "// Generado por branding/nexus-3d/build_assets.py. No editar a mano.\n"
        "// Coordenadas en píxeles de la imagen de la cabeza (nexus-head.webp).\n"
        f"export const MASCOT_LAYOUT = {json.dumps(data, indent=4)} as const;\n"
    )
    for f in sorted(OUT.glob("*.webp")):
        print(f"{f.name}: {f.stat().st_size / 1024:.1f} kB")
    print(f"layout -> {LAYOUT_TS.relative_to(ROOT)}")


main()
