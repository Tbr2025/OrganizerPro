import sys
from rembg import remove
from PIL import Image

if len(sys.argv) != 3:
    print("Usage: remove_bg.py input_path output_path")
    sys.exit(1)

input_path = sys.argv[1]
output_path = sys.argv[2]

with Image.open(input_path) as input_image:
    output_image = remove(input_image)
    output_image.save(output_path)
    print(f"Background removed and saved to {output_path}")
