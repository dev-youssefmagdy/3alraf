"""Export the SigLIP vision tower (google/siglip-base-patch16-224) to ONNX, L2-normalised output.

usage: python export_onnx.py <output.onnx>
Runs once, in a throw-away venv with requirements-export.txt (torch + transformers). Verifies ONNX Runtime == torch (cosine).
"""
import sys

import numpy as np
import torch
from transformers import SiglipModel

REPO = 'google/siglip-base-patch16-224'


class Vision(torch.nn.Module):
    def __init__(self, model):
        super().__init__()
        self.model = model

    def forward(self, pixel_values):
        v = self.model.get_image_features(pixel_values=pixel_values)
        if not isinstance(v, torch.Tensor):  # transformers >= 5 returns an output object
            v = v.pooler_output
        return torch.nn.functional.normalize(v, dim=-1)


def main(out: str) -> None:
    vision = Vision(SiglipModel.from_pretrained(REPO).eval()).eval()
    torch.onnx.export(vision, (torch.zeros(1, 3, 224, 224),), out, input_names=['pixel_values'], output_names=['embedding'],
                      dynamic_axes={'pixel_values': {0: 'b'}, 'embedding': {0: 'b'}}, opset_version=17, dynamo=False)

    import onnxruntime as ort

    x = np.random.default_rng(0).uniform(-1, 1, (2, 3, 224, 224)).astype(np.float32)
    with torch.inference_mode():
        ref = vision(torch.from_numpy(x)).numpy()
    got = ort.InferenceSession(out, providers=['CPUExecutionProvider']).run(None, {'pixel_values': x})[0]
    cos = float((ref * got).sum(axis=1).min())
    print(f'exported {out}: dim={got.shape[1]} min cosine torch vs onnx={cos:.7f}')
    if got.shape[1] != 768 or cos < 0.9999:
        raise SystemExit('export check failed')


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit(__doc__)
    main(sys.argv[1])
