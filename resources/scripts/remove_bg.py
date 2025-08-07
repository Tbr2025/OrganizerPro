import os
os.environ["NUMBA_CACHE_DIR"] = "/tmp"  # Optional: redirect cache if needed
os.environ["NUMBA_DISABLE_CACHE"] = "1"

from rembg import remove
from PIL import Image
import sys

input_path = sys.argv[1]
output_path = sys.argv[2]

with open(input_path, 'rb') as input_file:
    input_data = input_file.read()

output_data = remove(input_data)

with open(output_path, 'wb') as output_file:
    output_file.write(output_data)

print(f"Background removed and saved to {output_path}")
