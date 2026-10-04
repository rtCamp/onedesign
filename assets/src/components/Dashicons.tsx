interface RenderIconProps {
	sitesHealthCheckResult?: Record<
		string,
		{ success?: boolean } | undefined
	>;
	id: number | string;
}

/**
 * Render the appropriate dashicon based on health check result.
 *
 * @param props                        - Component props.
 * @param props.sitesHealthCheckResult - Health check results keyed by site id.
 * @param props.id                     - Site ID.
 * @return The rendered dashicon element.
 */
const renderIcon = ( {
	sitesHealthCheckResult,
	id,
}: RenderIconProps ): JSX.Element => {
	const siteId = String( id );
	return sitesHealthCheckResult?.[ siteId ] &&
		! sitesHealthCheckResult?.[ siteId ]?.success ? (
		<span className="dashicons dashicons-warning"></span>
	) : (
		<span className="dashicons dashicons-yes-alt"></span>
	);
};

export { renderIcon };
