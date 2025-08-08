import sys
import os
from rembg import remove
from PIL import Image

# Set cache environment paths
os.environ["XDG_CACHE_HOME"] = os.environ.get("XDG_CACHE_HOME", "/tmp/rembg-cache")
os.environ["NUMBA_CACHE_DIR"] = os.environ.get("NUMBA_CACHE_DIR", "/tmp/numba-cache")

input_path = sys.argv[1]
output_path = sys.argv[2]

try:
    # Read input image
    with open(input_path, 'rb') as i:
        input_data = i.read()

    # Remove background
    output_data = remove(input_data)

    # Save intermediate image
    with open(output_path, 'wb') as o:
        o.write(output_data)

    # Open as RGBA image for cropping
    img = Image.open(output_path).convert("RGBA")

    # Get bounding box of non-transparent pixels
    bbox = img.getbbox()
    if bbox:
        img = img.crop(bbox)

    # Save cropped image (keep transparency)
    img.save(output_path, format="PNG")

    print("Background removal & auto-cropping successful.")

except Exception as e:
    print(f"Error: {e}")
    sys.exit(1)
