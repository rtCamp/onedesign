/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

interface Pattern {
	categories?: string[];
}

interface PatternCategory {
	name: string;
	label: string;
}

interface CategoryProps {
	activeCategory: string;
	setActiveCategory: ( category: string ) => void;
	isOpen: boolean;
	basePatterns: Pattern[];
}

/**
 * Category component displays a list of pattern categories.
 *
 * @param props                   - Component properties.
 * @param props.activeCategory    - Currently active category.
 * @param props.setActiveCategory - Function to set the active category.
 * @param props.isOpen            - Indicates if the category list is open.
 * @param props.basePatterns      - List of base patterns to filter categories.
 * @return Rendered component.
 */
const Category = ( {
	activeCategory,
	setActiveCategory,
	isOpen,
	basePatterns,
}: CategoryProps ): JSX.Element => {
	const [ categories, setCategories ] = useState< PatternCategory[] >( [] );
	const [ categoryError, setCategoryError ] = useState( '' );

	const fetchPatternCategories = useCallback( async () => {
		try {
			if ( ! basePatterns || basePatterns.length === 0 ) {
				setCategoryError( __( 'No categories found', 'onedesign' ) );
				setCategories( [] );
				return;
			}

			const baseSitePatternCategories = await apiFetch< {
				categories: PatternCategory[];
			} >( {
				path: `/onedesign/v1/pattern-categories`,
			} );

			const patternCategoriesSet = new Set(
				basePatterns.flatMap( ( pattern ) =>
					Array.isArray( pattern.categories )
						? pattern.categories
						: []
				)
			);

			// Filter categories that are actually used in `basePatterns`.
			const categoriesWithPatterns =
				baseSitePatternCategories.categories.filter( ( category ) =>
					patternCategoriesSet.has( category.name )
				);

			setCategories( categoriesWithPatterns );
			if ( categoriesWithPatterns.length === 0 ) {
				setCategoryError( __( 'No categories found', 'onedesign' ) );
			} else {
				setCategoryError( '' );
			}
		} catch {
			setCategoryError(
				__( 'Error fetching pattern categories', 'onedesign' )
			);
		}
	}, [ basePatterns ] );

	useEffect( () => {
		if ( isOpen ) {
			fetchPatternCategories();
		}
	}, [ isOpen, fetchPatternCategories ] );

	if ( categoryError ) {
		return (
			<div className="library-sidebar">
				<div className="category-list">
					<div className="category-item">{ categoryError }</div>
				</div>
			</div>
		);
	}

	return (
		<div className="library-sidebar">
			<div className="category-list">
				<div className="category-item column-heading">
					{ __( 'Pattern Categories', 'onedesign' ) }
				</div>
				<Button
					className={ `category-item ${
						activeCategory === 'All' ? 'active' : ''
					}` }
					onClick={ () => setActiveCategory( 'All' ) }
				>
					{ __( 'All', 'onedesign' ) }
				</Button>
				{ categories &&
					categories.length > 0 &&
					categories.map( ( category ) => (
						<Button
							key={ category.name }
							className={ `category-item ${
								activeCategory === category.name ? 'active' : ''
							}` }
							onClick={ () => setActiveCategory( category.name ) }
						>
							{ category.label }
						</Button>
					) ) }
			</div>
		</div>
	);
};

export default Category;
