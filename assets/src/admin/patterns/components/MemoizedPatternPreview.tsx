/**
 * WordPress dependencies
 */
import { memo, useMemo } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';

interface PatternData {
	name?: string;
	title?: string;
	content?: string;
	category_labels?: Record< string, string >;
}

interface MemoizedPatternPreviewProps {
	pattern: PatternData;
	isSelected: boolean;
	onSelect: ( pattern: PatternData ) => void;
	isCheckBoxRequired?: boolean;
	providerSite?: string | false;
}

/**
 * Pattern content preview.
 *
 * @param props              - Component properties.
 * @param props.parsedBlocks - Parsed blocks to preview.
 * @return Rendered component.
 */
const PatternPreviewContent = memo(
	( { parsedBlocks }: { parsedBlocks: ReturnType< typeof parse > } ) => {
		return (
			<div className="onedesign-pattern-preview">
				<BlockPreview blocks={ parsedBlocks } viewportWidth={ 1200 } />
			</div>
		);
	}
);

/**
 * Separate memoized component for pattern categories.
 *
 * @param props            - Component properties.
 * @param props.categories - Categories object.
 * @return Rendered component.
 */
const PatternCategories = memo(
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
			<div className="onedesign-pattern-categories">
				<p>{ __( 'Categories:', 'onedesign' ) }</p>
				{ categoryValues.map( ( category, i ) => (
					<span
						key={ `${ category }-${ i }` }
						className="onedesign-pattern-category"
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
 * @param props.providerSite - Provider site name.
 * @return Rendered component.
 */
const ProviderSiteInfo = memo(
	( { providerSite }: { providerSite?: string | false } ) => {
		if ( ! providerSite ) {
			return null;
		}

		return (
			<div className="onedesign-pattern-provider-site-name">
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
 * Pattern preview card with selection controls.
 *
 * @param props                    - Component properties.
 * @param props.pattern            - Pattern to preview.
 * @param props.isSelected         - Whether the pattern is selected.
 * @param props.onSelect           - Selection handler.
 * @param props.isCheckBoxRequired - Whether to render the selection checkbox.
 * @param props.providerSite       - Provider site name, if any.
 * @return Rendered component.
 */
const MemoizedPatternPreview = memo(
	( {
		pattern,
		isSelected,
		onSelect,
		isCheckBoxRequired = true,
		providerSite = false,
	}: MemoizedPatternPreviewProps ) => {
		const parsedBlocks = useMemo(
			() => parse( pattern?.content ?? '' ),
			[ pattern?.content ]
		);

		const patternTitle = pattern?.title ?? pattern?.name;

		return (
			<div
				className="onedesign-pattern-wrapper"
				onClick={ () => onSelect( pattern ) }
				role="button"
				tabIndex={ 0 }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						onSelect( pattern );
					}
				} }
			>
				<div className="onedesign-pattern-title-wrapper">
					{ isCheckBoxRequired && (
						<CheckboxControl
							checked={ isSelected }
							onChange={ () => onSelect( pattern ) }
							onClick={ ( e ) => {
								e.stopPropagation();
							} }
							__nextHasNoMarginBottom
						/>
					) }
					<span className="onedesign-pattern-title">
						{ patternTitle }
					</span>
				</div>

				{ /* The preview that shouldn't re-render */ }
				<PatternPreviewContent parsedBlocks={ parsedBlocks } />

				{ /* Other info that can re-render if needed */ }
				<ProviderSiteInfo providerSite={ providerSite } />
				<PatternCategories categories={ pattern?.category_labels } />
			</div>
		);
	},
	( prevProps, nextProps ) => {
		return (
			prevProps.pattern.name === nextProps.pattern.name &&
			prevProps.pattern.content === nextProps.pattern.content &&
			prevProps.isSelected === nextProps.isSelected &&
			prevProps.isCheckBoxRequired === nextProps.isCheckBoxRequired &&
			prevProps.providerSite === nextProps.providerSite
		);
	}
);

export default MemoizedPatternPreview;
