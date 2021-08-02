import * as Element from '@wordpress/element';
import * as Components from '@wordpress/components';
import * as I18n from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

declare global {
	const wp: {
		element: typeof Element;
		components: typeof Components;
		i18n: typeof I18n;
		apiFetch: typeof apiFetch;
		// There are more things here but these are the ones we care about
	};

	// const REST: {
	// 	url: string;
	// 	nonce: string;
	// };

	const PhobosAuth: {
		actions: string[];
		url: string;
	};
}
