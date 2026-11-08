# Media generation

Status: **no image or video generation provider is configured.** Requirement: `MCP.md` §21.4.

## What happens today
- Claude reads product data, images and existing assets through the MCP tools, then writes concepts, captions, briefs, scripts and storyboards.
- `create_product_social_content_suggestion` and `create_media_asset_suggestion` save those as suggestions. Once approved and applied, they become a private draft item in the library with no file (kind `post`, status `draft`, source `suggestion`).
- A person makes the actual file, in Canva or elsewhere, and uploads it at `/marketer/media`. The item then becomes an image or video at version 1 and goes to review.
- Nothing is posted to a social network.

## Rules
- A script or storyboard is not a video. Never report a file as created unless a real file exists in the library.
- Canva: the store keeps only a link, limited to `https://canva.com` and `https://www.canva.com` (`App\Support\Marketing\CanvaUrl`). Canva is connected to Claude directly, not to the store.
- Everything stays private and goes through review. See [SOCIAL_MEDIA_LIBRARY.md](SOCIAL_MEDIA_LIBRARY.md).

## Adding a provider later
Not built. A provider would need its own explicit configuration, must verify the returned asset before saving it, and must store it through `SocialMediaLibraryService` so the same validation, privacy and review apply.
