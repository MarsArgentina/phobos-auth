const NODE_ENV = process.env.NODE_ENV || 'development';
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const ESLintPlugin = require( 'eslint-webpack-plugin' );
const path = require( 'path' );

module.exports = {
	mode: NODE_ENV,
	context: path.resolve( __dirname ),
	entry: {
		admin: './admin/index.tsx',
	},
	output: {
		path: __dirname,
		filename: './build/[name].js',
	},
	module: {
		rules: [
			{
				test: /.(js|ts|jsx|tsx)$/,
				use: [
					{
						loader: 'babel-loader',
						options: {
							presets: [
								'@babel/preset-env',
								[
									'@babel/preset-typescript',
									{
										jsxPragma: 'wp.element.createElement',
										jsxPragmaFrag: 'wp.element.Fragment',
									},
								],
							],
							plugins: [
								[
									'@babel/plugin-transform-react-jsx',
									{
										pragma: 'wp.element.createElement',
										pragmaFrag: 'wp.element.Fragment',
									},
								],
							],
						},
					},
				],
				exclude: /node_modules/,
			},
			{
				test: /\.(css|scss)$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
					},
					'css-loader',
					{
						loader: 'postcss-loader',
						options: {
							postcssOptions: {
								plugins: [ require( 'autoprefixer' ) ],
							},
						},
					},
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: './build/[name].css',
		} ),
		new ESLintPlugin( {
			extensions: [ 'js', 'ts', 'jsx', 'tsx' ],
		} ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve( __dirname, 'index.php' ),
					to: path.resolve( __dirname, 'build' ),
				},
			],
		} ),
	],
	resolve: {
		alias: {
			react: path.resolve( __dirname, 'stubs/react' ),
			'react-dom': path.resolve( __dirname, 'stubs/react' ),
		},
		extensions: [ '.ts', '.js', '.tsx', '.jsx', '.json' ],
	},
};
