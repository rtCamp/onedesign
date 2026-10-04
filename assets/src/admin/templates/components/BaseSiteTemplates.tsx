/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import MemoizedTemplatePreview from './MemoizedTemplatePreview';

type TemplateId = number | string;

interface Template {
	id?: TemplateId;
	name?: string;
	[ key: string ]: unknown;
}

interface BaseSiteTemplatesProps {
	filteredTemplates: Template[];
	currentPage: number;
	PER_PAGE: number;
	selectedTemplates: TemplateId[];
	handleTemplateSelection: ( id?: TemplateId ) => void;
}

/**
 * BaseSiteTemplates component.
 *
 * @param props                         - Component props.
 * @param props.filteredTemplates       - Array of filtered templates to display.
 * @param props.currentPage             - Current page number for pagination.
 * @param props.PER_PAGE                - Number of templates to display per page.
 * @param props.selectedTemplates       - Array of selected template IDs.
 * @param props.handleTemplateSelection - Function to handle template selection.
 * @return The rendered component.
 */
const BaseSiteTemplates = ( {
	filteredTemplates,
	currentPage,
	PER_PAGE,
	selectedTemplates,
	handleTemplateSelection,
}: BaseSiteTemplatesProps ): JSX.Element => {
	const renderTemplates = () => {
		if ( filteredTemplates.length === 0 ) {
			return (
				<div className="onedesign-no-templates">
					<p>{ __( 'No templates found.', 'onedesign' ) }</p>
				</div>
			);
		}

		return (
			<div className="onedesign-templates-grid">
				{ filteredTemplates
					.slice( 0, currentPage * PER_PAGE )
					.map( ( template ) => {
						return (
							<MemoizedTemplatePreview
								key={ template?.name }
								template={ template }
								isCheckBoxRequired
								onSelect={ () =>
									handleTemplateSelection( template?.id )
								}
								isSelected={ selectedTemplates.includes(
									template?.id ?? ''
								) }
							/>
						);
					} ) }
			</div>
		);
	};

	return renderTemplates();
};

export default BaseSiteTemplates;
