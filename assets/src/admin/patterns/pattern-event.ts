/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * This File contains the code to add the Pattern Library Button.
 */

interface LibraryCache {
	gutenberg: HTMLElement | null;
	gutenbergEditorHeader: Element | null;
	switchModeTemplate: string;
	switchMode: Element | null;
	switchModeButton: Element | null;
}

interface DesignLibraryApp {
	libraryCache: LibraryCache;
	libraryCacheElements: () => void;
	createElementFromHTML: ( htmlString: string ) => Element | null;
	buildButton: () => void;
	init: () => void;
	addCustomEventOnButtonClick: () => void;
}

const globalWp = (
	window as unknown as {
		wp: { data: { subscribe: ( callback: () => void ) => void } };
	}
 ).wp;

window.addEventListener( 'DOMContentLoaded', function () {
	const DesignLibraryGutenbergApp: DesignLibraryApp = {
		libraryCache: {} as LibraryCache,
		libraryCacheElements() {
			this.libraryCache = {} as LibraryCache;
			this.libraryCache.gutenberg = document.getElementById( 'editor' );
			this.libraryCache.gutenbergEditorHeader =
				document.querySelector( '.edit-post-layout' );
			this.libraryCache.switchModeTemplate =
				document.getElementById( 'pattern-library-gutenberg-button' )
					?.innerHTML ?? '';
			this.libraryCache.switchMode = this.createElementFromHTML(
				this.libraryCache.switchModeTemplate
			);
			this.libraryCache.switchModeButton =
				this.libraryCache.switchMode?.querySelector(
					'#pattern-library-main-button'
				) ?? null;
			this.addCustomEventOnButtonClick();

			globalWp.data.subscribe( () => {
				setTimeout( () => {
					this.buildButton();
				}, 1 );
			} );
		},
		createElementFromHTML( htmlString: string ): Element | null {
			const div = document.createElement( 'div' );
			div.innerHTML = htmlString.trim();
			return div.firstChild as Element | null;
		},
		buildButton() {
			const { gutenberg, switchMode } = this.libraryCache;
			if (
				! gutenberg?.querySelector( '#pattern-library-button' ) &&
				switchMode
			) {
				gutenberg
					?.querySelector( '.edit-post-header-toolbar' )
					?.appendChild( switchMode );
			}
		},
		init() {
			this.libraryCacheElements();
		},
		addCustomEventOnButtonClick() {
			this.libraryCache.switchModeButton?.addEventListener(
				'click',
				() => {
					window.console.log( 'Firing Custom Event' );
					const designLibraryModalOpenEvent = new CustomEvent(
						'designLibraryModalOpen',
						{
							detail: {
								message: __(
									'Open the Pattern Library Modal!',
									'onedesign'
								),
							},
						}
					);

					document.dispatchEvent( designLibraryModalOpenEvent );
				}
			);
		},
	};

	DesignLibraryGutenbergApp.init();
} );
