/**
 * WordPress dependencies
 */
import { memo, useMemo } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';

interface TemplateData {
	name?: string;
	title?: string;
	content?: string;
	category_labels?: Record< string, string >;
}

interface MemoizedTemplatePreviewProps {
	template: TemplateData;
	isSelected: boolean;
	onSelect: ( template: TemplateData ) => void;
	isCheckBoxRequired?: boolean;
	providerSite?: string | false;
}

/**
 * Block preview content for a template.
 *
 * @param props              - Component properties.
 * @param props.parsedBlocks - Parsed blocks to render in the preview.
 * @return Rendered component.
 */
const TemplatePreviewContent = memo(
	( { parsedBlocks }: { parsedBlocks: ReturnType< typeof parse > } ) => {
		return (
			<div className="onedesign-template-preview">
				<BlockPreview blocks={ parsedBlocks } viewportWidth={ 1200 } />
			</div>
		);
	}
);

/**
 * Separate memoized component for template categories.
 *
 * @param props            - Component properties.
 * @param props.categories - Categories object from the template.
 * @return Rendered component.
 */
const TemplateCategories = memo(
	( {
		categories,
	}: {
		categories?: Record< string, string > | undefined;
	} ) => {
		if (
			! categories ||
			typeof categories !== 'object' ||
			Array.isArray( categories )
		) {
			return null;
		}

		const categoryValues = Object.values( categories ).filter( Boolean );

		if ( ! categoryValues.length ) {
			return null;
		}

		return (
			<div className="onedesign-template-categories">
				<p>{ __( 'Categories:', 'onedesign' ) }</p>
				{ categoryValues.map( ( category, i ) => (
					<span
						key={ `${ category }-${ i }` }
						className="onedesign-template-category"
					>
						{ category }
					</span>
				) ) }
			</div>
		);
	}
);

/**
 * Separate memoized component for provider site info.
 *
 * @param props              - Component properties.
 * @param props.providerSite - Name of the provider site.
 * @return Rendered component.
 */
const ProviderSiteInfo = memo(
	( { providerSite }: { providerSite?: string | false } ) => {
		if ( ! providerSite ) {
			return null;
		}

		return (
			<div className="onedesign-template-provider-site-name">
				<p>
					{ __( 'Provider Site:', 'onedesign' ) }
					<span className="onedesign-provider-site-name">
						{ providerSite }
					</span>
				</p>
			</div>
		);
	}
);

/**
 * Template preview card with selection controls.
 *
 * @param props                    - Component properties.
 * @param props.template           - Template to preview.
 * @param props.isSelected         - Whether the template is selected.
 * @param props.onSelect           - Selection handler.
 * @param props.isCheckBoxRequired - Whether to render the selection checkbox.
 * @param props.providerSite       - Provider site name, if any.
 * @return Rendered component.
 */
const MemoizedTemplatePreview = memo(
	( {
		template,
		isSelected,
		onSelect,
		isCheckBoxRequired = true,
		providerSite = false,
	}: MemoizedTemplatePreviewProps ) => {
		// Parse blocks only once when the component mounts
		const parsedBlocks = useMemo(
			() => parse( template?.content ?? '' ),
			[ template?.content ]
		);

		// Get template title
		const templateTitle = template?.title ?? template?.name;

		return (
			<div
				className="onedesign-template-wrapper"
				onClick={ ( e ) => {
					e.preventDefault();
					onSelect( template );
				} }
				role="button"
				tabIndex={ 0 }
				onKeyDown={ ( e ) => {
					if ( e.code === 'Enter' || e.code === 'Space' ) {
						e.preventDefault();
						onSelect( template );
					}
				} }
			>
				<div className="onedesign-template-title-wrapper">
					{ isCheckBoxRequired && (
						<CheckboxControl
							checked={ isSelected }
							onChange={ () => onSelect( template ) }
							onClick={ ( e ) => {
								e.stopPropagation();
							} }
							__nextHasNoMarginBottom
						/>
					) }
					<span className="onedesign-template-title">
						{ templateTitle }
					</span>
				</div>

				{ /* The preview that shouldn't re-render */ }
				<TemplatePreviewContent parsedBlocks={ parsedBlocks } />

				{ /* Other info that can re-render if needed */ }
				<ProviderSiteInfo providerSite={ providerSite } />
				<TemplateCategories categories={ template?.category_labels } />
			</div>
		);
	},
	( prevProps, nextProps ) => {
		// Only re-render if these specific properties change
		return (
			prevProps.template.name === nextProps.template.name &&
			prevProps.template.content === nextProps.template.content &&
			prevProps.isSelected === nextProps.isSelected &&
			prevProps.isCheckBoxRequired === nextProps.isCheckBoxRequired &&
			prevProps.providerSite === nextProps.providerSite
		);
	}
);

export default MemoizedTemplatePreview;
