# Laravel File and Media Handling Package

### Version Compatibility

| Laravel Version |             This Package Version | Branch |
|----------------:|---------------------------------:|-------:|
|             v10 |                              5.x |    5.x |  
|              v9 |                              4.x |    4.x |  
|              v8 |                              3.x |    3.0 |  
|           v6/v7 | See [CHANGELOG.md](CHANGELOG.md) |        |

## Installation

Add the private repository in your 'composer.json` file.
```
"repositories": [
    {
        "type":"vcs",
        "url":"git@bitbucket.org:elegantmedia/laravel-media-manager.git"
    }
]
```

Add the repository to the required list on composer.json
`composer require emedia/laravel-media-manager`

## Publish Configuration files

```
php artisan vendor:publish --provider="Intervention\Image\ImageServiceProviderLaravelRecent"
```

## Usage


## Common Issues



## Contributing

- Found a bug? Report as an issue and if you can, submit a pull request.
- Please see [CONTRIBUTING](CONTRIBUTING.md) and for details.

Copyright (c) Elegant Media.
