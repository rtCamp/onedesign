/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, type Mock, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TemplateModal from '@/admin/templates/components/TemplateModal';

// The real children load @wordpress/blocks, which Vitest can't import.
vi.mock( '@/admin/templates/components/BaseSiteTemplates', () => ( {
	default: () => <div>base-templates</div>,
} ) );
vi.mock( '@/admin/templates/components/BrandSiteTemplates', () => ( {
	default: () => <div>brand-templates</div>,
} ) );

describe( 'TemplateModal', () => {
	beforeEach( () => {
		( global.fetch as Mock ).mockResolvedValue( {
			json: async () => ( { success: true, templates: [] } ),
		} );
	} );

	it( 'renders the template library modal shell', async () => {
		render( <TemplateModal /> );
		expect(
			await screen.findByText( 'Template Library' )
		).toBeInTheDocument();
		expect(
			await screen.findByText( 'base-templates' )
		).toBeInTheDocument();
	} );
} );
