/* eslint-disable camelcase, no-console */
import { useFetch, usePostFetch } from '../../useFetch';

type Settings = {
	client_id: string | null;
	client_secret: string | null;
	enabled: boolean;
};

const { __ } = wp.i18n;

const { useState, useEffect } = wp.element;

const {
	BaseControl,
	Icon,
	Button,
	ExternalLink,
	PanelBody,
	PanelRow,
	Placeholder,
	Spinner,
	ToggleControl,
} = wp.components;

export const DiscordLogo = () => {
	return (
		<svg
			className="phobos-discord-logo"
			viewBox="0 0 24 24"
			xmlns="http://www.w3.org/2000/svg"
		>
			<path
				d="m19.875 4.778c-1.448-.664-3.001-1.154-4.625-1.434-.03-.005-.059.008-.074.035-.2.355-.421.819-.576 1.183-1.746-.261-3.484-.261-5.194 0-.155-.372-.384-.828-.585-1.183-.015-.026-.045-.04-.074-.035-1.623.279-3.176.769-4.625 1.434-.013.005-.023.014-.03.026-2.945 4.4-3.752 8.693-3.357 12.932.002 .021.013 .041.03 .053 1.943 1.427 3.826 2.294 5.673 2.868.03 .009.061-.002.08-.026.437-.597.827-1.226 1.161-1.888.02-.039.001-.085-.039-.1-.618-.234-1.206-.52-1.772-.845-.045-.026-.048-.09-.007-.121.119-.089.238-.182.352-.276.021-.017.049-.021.073-.01 3.718 1.698 7.744 1.698 11.418 0 .024-.012.053-.008.074 .009.114 .094.233 .188.353 .277.041 .031.038 .095-.006.121-.566.331-1.154.61-1.773.844-.04.015-.058.062-.038.101 .341.661 .731 1.29 1.16 1.887.018 .025.05 .036.08 .027 1.856-.574 3.739-1.441 5.682-2.868.017-.013.028-.032.03-.052.474-4.901-.793-9.158-3.359-12.932-.006-.013-.017-.022-.03-.027zm-11.641 10.377c-1.119 0-2.042-1.028-2.042-2.29 0-1.262.905-2.29 2.042-2.29 1.146 0 2.06 1.037 2.042 2.29 0 1.262-.905 2.29-2.042 2.29zm7.549 0c-1.119 0-2.042-1.028-2.042-2.29 0-1.262.904-2.29 2.042-2.29 1.146 0 2.06 1.037 2.042 2.29 0 1.262-.896 2.29-2.042 2.29z"
				fill="currentColor"
			/>
		</svg>
	);
};

export const Discord = () => {
	const options = {
		path: `/phobos/auth/settings/discord`,
	};

	const { data, ...getResult } = useFetch< Settings >( options, [] );

	const { post, ...postResult } = usePostFetch( options, [] );

	if ( getResult.status === 'error' ) console.log( getResult.error );
	if ( postResult.status === 'error' ) console.log( postResult.error );

	const [ clientId, setClientId ] = useState< string >( '' );
	const [ clientSecret, setClientSecret ] = useState< string >( '' );
	const [ enabled, setEnabled ] = useState< boolean >( false );

	useEffect( () => {
		if ( ! data ) return;

		setClientId( data.client_id ?? '' );
		setClientSecret( data.client_secret ?? '' );
		setEnabled( data.enabled );
	}, [ data ] );

	const isValidId = /^[0-9]{18}$/.test( clientId );
	const isValidSecret = /^[a-zA-Z0-9_-]{32}$/.test( clientSecret );
	const isAPISaving = postResult.status === 'loading';

	if ( getResult.status !== 'success' ) {
		return (
			<PanelBody title={ __( 'Discord Settings' ) }>
				<Placeholder>
					{ getResult.status === 'loading' ? <Spinner /> : null }
					{ getResult.status === 'error' ? (
						<p>{ __( 'Failed to load Discord settings.' ) }</p>
					) : null }
				</Placeholder>
			</PanelBody>
		);
	}

	return (
		<PanelBody
			title={
				( (
					<>
						<Icon icon={ DiscordLogo() } size={ 20 } />
						{ __( 'Discord Settings' ) }
					</>
				 ) as unknown ) as string
			}
		>
			<PanelRow>
				<BaseControl
					label={ __( 'Client ID' ) }
					id="discord-client-id"
					className="codeinwp-text-field"
					help={
						isValidId || clientId === ''
							? ''
							: __(
									'The provided value is not a valid Client ID'
							  )
					}
				>
					<input
						type="text"
						value={ clientId }
						placeholder={ __( 'Discord Client ID' ) }
						disabled={ isAPISaving }
						onChange={ ( e ) =>
							setClientId( e.target.value.trim() )
						}
					/>
				</BaseControl>
			</PanelRow>
			<PanelRow>
				<BaseControl
					label={ __( 'Client Secret' ) }
					id="discord-client-secret"
					className="codeinwp-text-field"
					help={
						isValidSecret || clientSecret === ''
							? ''
							: __(
									'The provided value is not a valid Client Secret'
							  )
					}
				>
					<input
						type="text"
						value={ clientSecret }
						placeholder={ __( 'Discord Client Secret' ) }
						disabled={ isAPISaving }
						onChange={ ( e ) =>
							setClientSecret( e.target.value.trim() )
						}
					/>
				</BaseControl>
			</PanelRow>
			<PanelRow>
				<p>
					{ __(
						'You can find the your Client ID and Client Secret inside of the OAuth2 tab of your Discord Application.'
					) }
				</p>
			</PanelRow>

			<PanelRow>
				<BaseControl
					label={ __( 'Redirects' ) }
					id="discord-redirects"
					className="discord-redirects codeinwp-text-field"
				>
					<p>
						{ __(
							'Add the following URLs as Redirects in the OAuth2 tab of your Discord Application.'
						) }
					</p>
					<ul>
						{ PhobosAuth.actions.map( ( action, index ) => {
							return (
								<li
									key={ index }
								>{ `${ PhobosAuth.url }/discord/${ action }` }</li>
							);
						} ) }
					</ul>
				</BaseControl>
			</PanelRow>

			<PanelRow>
				<ToggleControl
					label={ __( 'Enable Discord Authentication' ) }
					checked={ enabled }
					onChange={ ( enable ) => setEnabled( enable ) }
				/>
			</PanelRow>

			<PanelRow>
				<Button
					isPrimary
					disabled={ isAPISaving }
					onClick={ () =>
						post( {
							client_id: isValidId ? clientId : undefined,
							client_secret: isValidSecret
								? clientSecret
								: undefined,
							enabled,
						} )
					}
				>
					{ __( 'Save' ) }
				</Button>

				{ postResult.status === 'error' ? (
					<p className="save-error">
						{ __(
							'There was an error while saving your settings.'
						) }
					</p>
				) : null }

				<ExternalLink href="https://discord.com/developers/applications#top">
					{ __( 'Discord Applications' ) }
				</ExternalLink>
			</PanelRow>
		</PanelBody>
	);
};
