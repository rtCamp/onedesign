import { memo, useMemo } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';

export interface LibraryItem {
	// The pattern's name, which selects and removes it, and tells which brand sites have it.
	key: string;
	title: string;
	description?: string | undefined;
	content: string;
	categoryLabels?: Record< string, string > | undefined;
}

interface PreviewCardProps {
	item: LibraryItem;
	isSelected: boolean;
	onToggle: ( key: string ) => void;
}

/**
 * A library item's block preview, with a checkbox to select it.
 *
 * @param props            - Component properties.
 * @param props.item       - The pattern.
 * @param props.isSelected - Whether it's selected.
 * @param props.onToggle   - Selects or deselects it.
 *
 * @return The card.
 */
const PreviewCard = ( { item, isSelected, onToggle }: PreviewCardProps ) => {
	const blocks = useMemo( () => parse( item.content ), [ item.content ] );
	const categories = Object.entries( item.categoryLabels ?? {} );

	return (
		<div
			className={ `onedesign-preview-card${
				isSelected ? ' is-selected' : ''
			}` }
		>
			<CheckboxControl
				className="onedesign-preview-card-title"
				label={ item.title }
				checked={ isSelected }
				onChange={ () => onToggle( item.key ) }
				__nextHasNoMarginBottom
			/>
			<div className="onedesign-preview-card-preview">
				<BlockPreview blocks={ blocks } viewportWidth={ 1200 } />
			</div>
			{ categories.length > 0 && (
				<p className="onedesign-preview-card-meta">
					{ __( 'Categories:', 'onedesign' ) }
					{ categories.map( ( [ slug, label ] ) => (
						<span
							key={ slug }
							className="onedesign-preview-card-tag"
						>
							{ label }
						</span>
					) ) }
				</p>
			) }
		</div>
	);
};

// Previews are slow to render, so a card only renders again when its own item or selection changes.
export default memo( PreviewCard );
