const path = require('path');
const CKEditorWebpackPlugin = require('@ckeditor/ckeditor5-dev-webpack-plugin');
const {styles} = require('@ckeditor/ckeditor5-dev-utils');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	entry: path.join(__dirname, 'src/main.js'),
	output: {
		path: path.resolve(__dirname, 'js'),
		chunkFilename: 'mail.[name].[contenthash].js',
		publicPath: '/js/',
		filename: 'mail.js'
	},
	node: {
		fs: 'empty'
	},
	module: {
		rules: [
			{
				test: /davclient/,
				use: 'exports-loader?dav'
			},
			{
				test: /ical/,
				use: 'exports-loader?ICAL'
			},
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
			{
				test: /\.(png|jpg|gif)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			},
			{
				test: /\.(svg)$/i,
				use: [
					{
						loader: 'url-loader'
					}
				],
				exclude: path.join(__dirname, 'node_modules', '@ckeditor')
			},
			{
				test: /ckeditor5-[^/\\]+[/\\]theme[/\\]icons[/\\][^/\\]+\.svg$/,
				loader: 'raw-loader'
			},
			{
				test: /ckeditor5-[^/\\]+[/\\].+\.css$/,
				loader: 'postcss-loader',
				options: styles.getPostCssConfig({
					themeImporter: {
						themePath: require.resolve('@ckeditor/ckeditor5-theme-lark'),
					},
					minify: true
				})
			}
		]
	},
	plugins: [
		// CKEditor needs its own plugin to be built using webpack.
		new CKEditorWebpackPlugin({
			// See https://ckeditor.com/docs/ckeditor5/latest/features/ui-language.html
			language: 'en'
		}),
		new VueLoaderPlugin()
	],
	resolve: {
		extensions: ['*', '.js', '.vue', '.json'],
		symlinks: false
	}
};
