"""Image preprocessing shared by indexing and queries (IMAGE_SEARCH_PLAN.md §8.3).

1. EXIF-transpose (phone photos)
2. RGB; transparent pixels (RGBA / LA / P with transparency) composited onto WHITE (catalog photos are on white)
3. pad to a square on white (never crop or stretch)
4. resize to 224x224, bicubic
5. scale to [0, 1] then normalise with mean = std = 0.5  ->  float32 CHW
"""
from io import BytesIO

import numpy as np
from PIL import Image, ImageOps, UnidentifiedImageError

SIZE = 224
WHITE = (255, 255, 255)


class InvalidImage(ValueError):
    """The bytes are not an image Pillow can decode safely."""


def decode(data: bytes, max_pixels: int) -> Image.Image:
    if not data:
        raise InvalidImage('empty body')
    Image.MAX_IMAGE_PIXELS = max_pixels  # Pillow raises DecompressionBombError above 2x this, warns above 1x
    try:
        img = Image.open(BytesIO(data))
        if img.width * img.height > max_pixels:
            raise InvalidImage(f'image too large ({img.width}x{img.height})')
        if img.format == 'JPEG':
            img.draft('RGB', (SIZE * 4, SIZE * 4))  # fast DCT downscale; still >= 4x the model size
        img.load()
    except InvalidImage:
        raise
    except (UnidentifiedImageError, Image.DecompressionBombError, OSError, ValueError, SyntaxError) as e:
        raise InvalidImage(f'cannot decode image: {e.__class__.__name__}') from None
    return img


def to_rgb_on_white(img: Image.Image) -> Image.Image:
    img = ImageOps.exif_transpose(img) or img
    if img.mode == 'P' and 'transparency' in img.info:
        img = img.convert('RGBA')
    if img.mode in ('RGBA', 'LA', 'PA', 'RGBa', 'La'):
        rgba = img.convert('RGBA')
        bg = Image.new('RGB', rgba.size, WHITE)
        bg.paste(rgba, mask=rgba.getchannel('A'))
        return bg
    return img.convert('RGB')


def pad_square(img: Image.Image) -> Image.Image:
    side = max(img.size)
    if img.width == img.height:
        return img
    bg = Image.new('RGB', (side, side), WHITE)
    bg.paste(img, ((side - img.width) // 2, (side - img.height) // 2))
    return bg


def pixels(img: Image.Image) -> np.ndarray:
    """PIL image -> float32 array (1, 3, 224, 224) ready for the SigLIP vision tower."""
    sq = pad_square(to_rgb_on_white(img)).resize((SIZE, SIZE), Image.BICUBIC)
    a = np.asarray(sq, dtype=np.float32) / 255.0
    a = (a - 0.5) / 0.5
    return a.transpose(2, 0, 1)[None, ...].astype(np.float32, copy=False)
