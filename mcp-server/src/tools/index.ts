import type { AnyTool } from './define.js';
import { mediaTools } from './media.js';
import { metaTools } from './meta.js';
import { readTools } from './read.js';
import { suggestionTools } from './suggestions.js';

export const ALL_TOOLS: AnyTool[] = [...readTools, ...suggestionTools, ...mediaTools, ...metaTools];
