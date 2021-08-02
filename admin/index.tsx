/* eslint-disable camelcase */

/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

const { render } = wp.element;
const { Icon } = wp.components;

/**
 * Internal dependencies
 */
import './style.scss';
import { Discord } from '../providers/discord';

const App = () => {
	return (
		<>
			<div className="codeinwp-header">
				<div className="codeinwp-container">
					<div className="codeinwp-logo">
						<h1>
							<Icon icon="admin-network" size={ 40 } />
							{ __( 'Authentication' ) }
						</h1>
					</div>
				</div>
			</div>
			<div className="codeinwp-main">
				<Discord />
			</div>
		</>
	);
};

window.onload = () => {
	const element = document.getElementById( 'phobos-auth-admin' );

	console.log( element ); //eslint-disable-line
	render( <App />, element );
};
