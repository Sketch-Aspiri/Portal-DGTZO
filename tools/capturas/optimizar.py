"""Reduce el peso de las capturas (paleta de 256 colores). Uso: python optimizar.py <carpeta> [...]"""
import pathlib
import sys

from PIL import Image

# Colores pequeños pero importantes (marcas numeradas y avisos) que la paleta automática perdería.
FIJOS = [(225, 29, 72), (220, 38, 38), (255, 255, 255), (254, 243, 199), (16, 185, 129)]

antes = despues = 0
for carpeta in sys.argv[1:]:
    for archivo in pathlib.Path(carpeta).rglob('*.png'):
        with Image.open(archivo) as im:
            if im.mode == 'P':
                continue
            color = im.convert('RGB')
        base = color.quantize(colors=256 - len(FIJOS), method=Image.Quantize.MEDIANCUT)
        paleta = Image.new('P', (1, 1))
        paleta.putpalette(base.getpalette()[:3 * (256 - len(FIJOS))] + [v for c in FIJOS for v in c])
        reducida = color.quantize(palette=paleta, dither=Image.Dither.NONE)
        antes += archivo.stat().st_size
        reducida.save(archivo, optimize=True)
        despues += archivo.stat().st_size
if antes:
    print(f'Capturas optimizadas: {antes / 1e6:.1f} MB -> {despues / 1e6:.1f} MB')
