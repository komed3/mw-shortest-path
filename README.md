# MediaWiki Extension: ShortestWikiPath

The MediaWiki Extension allows you to search for the shortest connection between two articles of the Wiki project. It uses the Dijkstra algorithm.

__Tested with MediaWiki 1.28, 1.29, 1.30, 1.31__

## Introduction

To find the shortest route between all the links in the wiki project, the extension uses the Dijkstra algorithm. To do this, only start and target articles must be entered in a form. You can also activate the reverse search, in which case the algorithm will also try to link from the target to the start article.

The algorithm of Dijkstra calculates a shortest route between the given start node and all other nodes. For this extension, all edges are weighted equally, since links are equivalent.

## Installation

Download all necessary files, unzip there and place it in the `ShortestWikiPath` folder in the `extensions/` directory of your MediaWiki installation.

In the next step, add the following to your `LocalSettings.php`:

```
wfLoadExtension( 'ShortestWikiPath' );
```

Now you can check via `Special:Version` whether the extension was installed successfully.

## Supported languages

- English
- German (Deutsch)

## License

[MIT](https://choosealicense.com/licenses/mit/)
