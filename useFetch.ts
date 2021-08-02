import { APIFetchOptions } from '@wordpress/api-fetch';

const { __ } = wp.i18n;

wp.apiFetch.setFetchHandler( async ( nextOptions ) => {
	const { url, path, data, parse = true, ...remainingOptions } = nextOptions;
	let { body, headers } = nextOptions;

	// Merge explicitly-provided headers with default values.
	headers = { Accept: 'application/json, */*;q=0.1', ...headers };

	// The `data` property is a shorthand for sending a JSON body.
	if ( data ) {
		body = JSON.stringify( data );
		headers[ 'Content-Type' ] = 'application/json';
	}

	try {
		const response = await window.fetch(
			// fall back to explicitly passing `window.location` which is the behavior if `undefined` is passed
			url || path || window.location.href,
			{
				credentials: 'include',
				...remainingOptions,
				body,
				headers,
			}
		);

		if ( response.status < 200 || response.status >= 300 ) {
			throw response;
		}

		if ( ! parse ) return response;

		if ( response.status === 204 ) return null;

		if ( ! response.json ) throw response;

		return await response.json();
	} catch ( response ) {
		if ( parse ) {
			const invalidJsonError = {
				code: 'invalid_json',
				message: __( 'The response is not a valid JSON response.' ),
			};

			if ( response instanceof Error ) {
				throw {
					code: 'fetch_error',
					message: `(${ response.name }) ${ response.message }`,
				};
			}

			if ( ! response || ! response.json ) {
				throw invalidJsonError;
			}

			throw await ( response as Response ).json().catch( () => {
				throw invalidJsonError;
			} );
		}

		throw response;
	}
} );

export function useFetch< Result >(
	options: APIFetchOptions,
	dependencies: ReadonlyArray< any >
) {
	const ref = wp.element.useRef( options );

	wp.element.useEffect( () => {
		ref.current = options;
	}, [ options ] );

	return baseFetch< Result >( ref, dependencies );
}

function baseFetch< Result >(
	ref: React.MutableRefObject< APIFetchOptions >,
	dependencies: ReadonlyArray< any >
) {
	const [ refetch, setRefetch ] = wp.element.useState< boolean >( false );
	const [ status, setStatus ] = wp.element.useState( 'idle' );
	const [ data, setData ] = wp.element.useState< Result >();
	const [ error, setError ] = wp.element.useState< unknown >();

	wp.element.useEffect( () => {
		if ( ! ref.current ) return;

		const abort = new AbortController();

		const fetchData = async () => {
			setStatus( 'loading' );
			try {
				const result = await wp.apiFetch< Result >( {
					signal: abort.signal,
					...ref.current,
				} );

				setData( result );
				setStatus( 'success' );
			} catch ( e: unknown ) {
				setError( e );
				setStatus( 'error' );
			}
		};

		fetchData();

		return () => abort.abort();
	}, [ ...dependencies, refetch ] );

	return {
		status,
		data,
		error,
		refetch: wp.element.useCallback( () => {
			setRefetch( ( prev ) => ! prev );
		}, [] ),
	} as const;
}

export function usePostFetch< Result >(
	options: APIFetchOptions,
	dependencies: ReadonlyArray< any >
) {
	const ref = wp.element.useRef< APIFetchOptions >( null );

	const result = baseFetch< Result >( ref, [ ...dependencies ] );

	return {
		...result,
		post: wp.element.useCallback(
			( data: any ) => {
				ref.current = {
					method: 'POST',
					data,
					...options,
				};
				result.refetch();
			},
			[ options ]
		),
	} as const;
}
