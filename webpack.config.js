const path = require("path");
const TerserPlugin = require("terser-webpack-plugin");

module.exports = {
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        extractComments: false,
      }),
    ],
  },
  mode: process.env.NODE_ENV === "production" ? "production" : "development",

  entry: {
    transparent: "./mobbex/views/src/Transparent.jsx",
  },

  output: {
    path: path.resolve(__dirname, "mobbex/views/js"),
    filename: "[name].min.js",
  },

  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader",
          options: {
            presets: ["@babel/preset-env", "@babel/preset-react"],
          },
        },
      },
      {
        test: /\.css$/,
        use: ["style-loader", "css-loader"],
      },
    ],
  },

  resolve: {
    extensions: [".js", ".jsx"],
  },

  devtool: false,
};
