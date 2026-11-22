# Build an AI Creative Studio for 3alraf Using ComfyUI

Act as a Senior AI Engineer, Creative Director, Product Photographer, Video Production Specialist, and Automation Engineer.

I want you to transform my existing local ComfyUI installation into a professional AI Creative Studio for my Egyptian e-commerce brand **3alraf (ع الرف)**.

Your mission is to build an automated system that creates professional product photography, advertising designs, and short commercial videos using AI.

The system must work locally on Ubuntu and use my existing NVIDIA GPU whenever possible.

## 1. Existing Environment

Operating System: Ubuntu 24.04

Hardware:
- NVIDIA GeForce RTX 5060
- 8GB VRAM
- 64GB RAM
- Intel Core i7-14700KF

Existing ComfyUI installation:

`~/AI-Studio/ComfyUI`

Python environment:

`~/AI-Studio/ComfyUI/.venv`

ComfyUI API:

`http://127.0.0.1:8188`

Currently installed models:
- z_image_turbo_nvfp4.safetensors
- qwen_3_4b_fp8_mixed.safetensors
- ae.safetensors

Current image generation engine:
- Z-Image Turbo
- ComfyUI

Claude Code will be the main AI agent and orchestration layer.

IMPORTANT:
- No MCP.
- No Figma dependency.
- No Docker.
- No paid APIs by default.
- Use ComfyUI HTTP API and local scripts.
- Do not break my existing ComfyUI installation or workflows.
- Do not download large models without checking their requirements and getting my approval.

## 2. Main Creative Studio Capabilities

Build three main production engines.

### ENGINE A: AI Product Photography

Input:
- Original product image.
- Product category.
- Optional advertising concept.
- Optional preferred background and colors.

Supported products:
- Cosmetics and skincare.
- Watches.
- Wallets.
- Handbags.
- Stainless steel accessories.
- Jewelry.
- Fashion accessories.

Required capabilities:

1. Analyze the original product image.
2. Identify the product category and visible characteristics.
3. Remove the original background when necessary.
4. Extract the product with a high-quality transparent mask.
5. Generate professional advertising backgrounds.
6. Preserve the original product pixels whenever possible.
7. Composite the original product onto the generated background.
8. Generate realistic shadows and reflections.
9. Apply professional studio lighting.
10. Generate multiple creative variations.

CRITICAL PRODUCT PRESERVATION RULE:

Never regenerate the product itself when the user wants an accurate product advertisement.

Preserve:
- Original shape.
- Original colors.
- Logos and branding.
- Product labels.
- Materials and textures.
- Buttons, zippers and small details.
- Packaging and printed text.

Prefer segmentation, background removal, compositing, and localized image editing over full image regeneration.

If the product needs AI reconstruction or modification, explicitly explain that this may change product details.

### ENGINE B: AI Advertising Design

Create complete advertising creatives ready for social media.

Required formats:

- Instagram Post: 1080x1080.
- Instagram Portrait: 1080x1350.
- Instagram Story: 1080x1920.
- Facebook Feed: 1080x1350.
- TikTok Cover: 1080x1920.

Design requirements:

- Premium commercial photography.
- Modern Egyptian e-commerce advertising style.
- Professional product positioning.
- Attractive composition.
- High-quality lighting.
- Realistic shadows.
- Beautiful backgrounds.
- Arabic RTL typography.
- Clear promotional messaging.
- Consistent brand identity.

Brand:
3alraf / ع الرف

Brand direction:
- Elegant and modern.
- Premium but accessible.
- Feminine and sophisticated when appropriate.
- Pink-inspired visual identity for cosmetics.
- Adapt the color palette to the actual product category.
- Do not force pink backgrounds on every product.

Arabic text must be rendered using proper Arabic shaping and RTL support through a suitable graphics library, not generated directly inside the diffusion model.

Support:
- Product name.
- Advertising headline.
- Promotional offer.
- Price when supplied.
- Call to action.
- Brand logo.

Never invent prices, discounts, product specifications, or promotional claims.

### ENGINE C: AI Product Video Studio

This is a major priority.

I want to turn original product images into professional commercial videos.

Target duration:
- 5 seconds.
- 10 seconds.
- 15 seconds.
- 20 seconds.
- 30 seconds.

Target output:
- MP4.
- H.264.
- 1080x1920 for vertical advertising.
- 1080x1080 for square campaigns.
- 1920x1080 for landscape campaigns.

Video generation capabilities:

1. Image-to-video.
2. Text-to-video.
3. Product showcase videos.
4. Cinematic camera movements.
5. Smooth zoom-in and zoom-out.
6. Slow camera rotation effects where appropriate.
7. Background animation.
8. Lighting and particle effects.
9. Professional transitions.
10. Multiple video variations.

The original product must remain visually consistent throughout the video.

Avoid:
- Changing the product shape.
- Distorting logos.
- Changing product colors.
- Adding imaginary product features.
- Unnatural product deformation.
- Unstable camera movement.

Investigate suitable ComfyUI video models and workflows, including:

- Wan 2.2 TI2V 5B.
- LTX-Video and suitable lightweight variants.
- Other actively maintained image-to-video models compatible with limited VRAM.

Evaluate actual hardware requirements before installation.

For 8GB VRAM:
- Investigate quantized models.
- Use CPU offloading where supported.
- Use tiled processing where supported.
- Use reduced resolution when necessary.
- Optimize frame count.
- Avoid CUDA Out of Memory.
- Measure actual generation performance.

If local video generation is too slow or impractical, propose an optional cloud provider integration, but do not activate paid services without approval.

For longer videos, investigate generating short clips and assembling them using FFmpeg.

## 3. AI Creative Director

Claude Code must act as an intelligent creative director.

When I provide a product image and say:

"Create an advertising campaign for this product."

You should automatically:

1. Inspect the product image.
2. Identify the product category.
3. Analyze colors, materials, shape, and visible details.
4. Suggest suitable creative directions.
5. Select a suitable generation workflow.
6. Generate advertising backgrounds.
7. Composite the original product.
8. Create Arabic advertising layouts.
9. Generate multiple creative variations.
10. Generate a short promotional video.
11. Review the results.
12. Save the final outputs.

Do not blindly use the same prompt for every product.

For example:

Cosmetics:
- Luxury beauty studio.
- Pink marble.
- Soft lighting.
- Clean skincare environment.

Watches:
- Dark luxury studio.
- Metallic reflections.
- Premium dramatic lighting.

Wallets:
- Elegant leather textures.
- Dark brown or beige environments.
- Warm cinematic lighting.

Stainless steel:
- Clean premium studio.
- Metallic reflections.
- Minimalist luxury compositions.

Handbags:
- Fashion editorial photography.
- Elegant interiors.
- Premium lifestyle environments.

## 4. Technical Architecture

Use the following architecture:

Claude Code:
- Creative direction.
- Prompt engineering.
- Workflow selection.
- Automation.
- Quality review.

ComfyUI:
- Image generation.
- Image editing.
- Background generation.
- Image-to-video.
- Video generation.

Python:
- Image processing.
- Segmentation.
- Background removal.
- Mask refinement.
- Image compositing.
- Workflow automation.

Pillow / OpenCV:
- Image composition.
- Resizing.
- Effects.
- Image manipulation.

FFmpeg:
- Video assembly.
- Audio integration.
- Transitions.
- Encoding.
- Export.

Use ComfyUI HTTP API for all generation tasks.

Create reusable ComfyUI JSON workflows for every production type.

## 5. Project Structure

Create a dedicated project directory:

`~/AI-Studio/3alraf-creative-studio/`

Suggested structure:

```text
3alraf-creative-studio/
├── inputs/
│   ├── products/
│   └── references/
├── outputs/
│   ├── product-photography/
│   ├── advertising/
│   ├── videos/
│   └── previews/
├── workflows/
│   ├── text-to-image/
│   ├── product-background/
│   ├── image-editing/
│   ├── image-to-video/
│   └── text-to-video/
├── scripts/
├── configs/
├── assets/
│   ├── branding/
│   ├── fonts/
│   └── logos/
├── logs/
└── README.md
```

Adapt the structure if you find a better architecture.

## 6. Quality Control

Implement a review stage.

Check:
- Product similarity to the original.
- Product shape preservation.
- Logo preservation.
- Image quality.
- Lighting consistency.
- Background quality.
- Arabic typography.
- Correct dimensions.
- Video smoothness.
- Export quality.

Generate a contact sheet of multiple variations so I can compare results.

Never claim that an image or video is commercially accurate without actually checking it.

## 7. Automation and User Experience

I want to interact with Claude naturally.

Examples:

"Create 3 premium advertisements for this wallet."

"Make a 15-second cinematic video for this watch."

"Create a pink luxury cosmetics campaign."

"Generate 5 different backgrounds for this handbag."

"Create an Instagram Story and a Facebook advertisement for this product."

"Make this product look like a luxury commercial photography shoot."

Claude should select the appropriate workflow and execute it automatically.

Do not ask me to manually configure ComfyUI nodes for every new product.

## 8. Implementation Strategy

Work in phases.

PHASE 1:
Inspect the existing environment, installed models, ComfyUI API, GPU memory, and available nodes.

PHASE 2:
Build the image processing and background removal pipeline.

PHASE 3:
Create professional product photography workflows.

PHASE 4:
Create Arabic advertising composition and social media templates.

PHASE 5:
Evaluate and install a suitable local video generation workflow.

PHASE 6:
Build image-to-video and video assembly automation.

PHASE 7:
Integrate all engines with Claude Code.

PHASE 8:
Test the complete system using actual 3alraf products.

For every phase:
- Explain what you are going to build.
- List dependencies.
- Estimate model download sizes.
- Explain VRAM requirements.
- Implement the phase.
- Test it.
- Document the result.

Do not skip testing.

Do not install unnecessary dependencies.

Do not overwrite existing models or workflows.

Do not make destructive changes without approval.

## 9. First Task

Do not start downloading models immediately.

First inspect my current ComfyUI environment and determine:

1. Which models are already installed.
2. Which workflows are available.
3. Whether the current Z-Image Turbo workflow works.
4. Which image editing workflows are missing.
5. Which background removal solution is best.
6. Which video generation model can realistically run on RTX 5060 8GB.
7. What additional storage is required.

Then present a detailed implementation plan with:
- Recommended models.
- Model sizes.
- VRAM requirements.
- Expected performance limitations.
- Installation commands.
- Architecture.
- Development phases.

Wait for my approval before downloading additional models or making major system changes.

The final goal is a professional AI-powered image and video advertising production studio for 3alraf, controlled directly through Claude Code without MCP.