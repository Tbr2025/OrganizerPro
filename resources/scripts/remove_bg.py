import sys
import os
os.environ["XDG_CACHE_HOME"] = os.environ.get("XDG_CACHE_HOME", "/tmp/rembg-cache")
os.environ["NUMBA_CACHE_DIR"] = os.environ.get("NUMBA_CACHE_DIR", "/tmp/numba-cache")

from rembg import remove
from PIL import Image

input_path = sys.argv[1]
output_path = sys.argv[2]

try:
    with open(input_path, 'rb') as i:
        input_data = i.read()

    output_data = remove(input_data)

    with open(output_path, 'wb') as o:
        o.write(output_data)

    print("Background removal successful.")

except Exception as e:
    print(f"Error: {e}")
    sys.exit(1)
