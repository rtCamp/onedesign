// `@types/wordpress__block-editor` doesn't declare `BlockPreview`, so augment the module with it.

import type { ComponentType } from 'react';
import type { parse } from '@wordpress/blocks';

declare module '@wordpress/block-editor' {
	export const BlockPreview: ComponentType< {
		blocks: ReturnType< typeof parse >;
		viewportWidth?: number;
	} >;
}
