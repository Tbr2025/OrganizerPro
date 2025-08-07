import sys
from rembg import remove
from PIL import Image
import os

if len(sys.argv) != 3:
    print("Usage: remove_bg.py input_path output_path")
    sys.exit(1)

input_path = sys.argv[1]
output_path = sys.argv[2]

if not os.path.exists(input_path):
    print(f"Input file does not exist: {input_path}")
    sys.exit(1)

try:
    with Image.open(input_path) as input_image:
        output_image = remove(input_image)
        output_image.save(output_path)
        print(f"Background removed and saved to {output_path}")
except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)
