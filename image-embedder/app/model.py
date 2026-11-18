"""SigLIP vision tower (ONNX Runtime, CPU). The exported graph already L2-normalises; we normalise again defensively."""
import numpy as np


class Embedder:
    def __init__(self, model_path: str, model_name: str, threads: int):
        import onnxruntime as ort

        so = ort.SessionOptions()
        so.intra_op_num_threads = threads
        so.inter_op_num_threads = 1
        so.graph_optimization_level = ort.GraphOptimizationLevel.ORT_ENABLE_ALL
        self.session = ort.InferenceSession(str(model_path), so, providers=['CPUExecutionProvider'])
        self.input_name = self.session.get_inputs()[0].name
        self.model_name = model_name
        self.dim = int(self.run(np.zeros((1, 3, 224, 224), dtype=np.float32)).shape[-1])  # warm-up + dimension check

    def run(self, batch: np.ndarray) -> np.ndarray:
        out = self.session.run(None, {self.input_name: batch})[0].astype(np.float32)
        norms = np.linalg.norm(out, axis=-1, keepdims=True)
        return out / np.maximum(norms, 1e-12)
