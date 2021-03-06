# SilverStripe Salsify
[![codecov](https://codecov.io/gh/dynamic/silverstripe-salsify/branch/master/graph/badge.svg)](https://codecov.io/gh/dynamic/silverstripe-salsify)
[![Build Status](https://travis-ci.com/dynamic/silverstripe-salsify.svg?branch=master)](https://travis-ci.com/dynamic/silverstripe-salsify)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dynamic/silverstripe-salsify/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dynamic/silverstripe-salsify/?branch=master)

Salsify integration for SilverStripe websites.

## Requirements

* SilverStripe ^4.0

## Installation

```
composer require dynamic/silverstripe-salsify
```

## License
See [License](license.md)

## Running the task
The task can be run from a browser window or the command line.
To run the task in the browser go to `dev/tasks` and find `Import products from salsify` or visit `dev/tasks/SalsifyImportTask`.

It is recommended to use the command line, because the task can easily time out in a browser.
To run the task in the command line sake must be installed and the command `sake dev/tasks/SalsifyImportTask` must be run.

## Example configuration
### Extensions
#### SalsifyIDExtension
It is recommended to add `Dyanmic\Salsify\ORM\SalsifyIDExtension` as an extension of any object being mapped to.
It will add a `SalsifyID` and `SalsifyUpdatedAt` field that can be mapped to.
The `SalsifyID` field is used in single object updates.

```yaml
MyObject:
  extensions:
    - Dyanmic\Salsify\ORM\SalsifyIDExtension
```

The `SalsifyID` and `SalsifyUpdatedAt` fields will stil need to be explicitly mapped in the mapper config.

```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      SalsifyID:
        salsifyField: 'salsify:id'
        unique: true
      SalsifyUpdatedAt: 'salsify:updated_at'
```

### Importer
Importers will run fetchers and mappers. Each importer needs to be passed an importerKey to its constructor.
For the rest of the readme `example` will be used for the services.
```yaml
SilverStripe\Core\Injector\Injector:
  Dynamic\Salsify\Model\Importer.example:
    class: Dynamic\Salsify\Model\Importer
    constructor:
      importerKey: example
```

### Fetcher
To set up a fetcher an api key and a channel id need to be provided.
The `apiKey` can be in the root Fetcher config, but can also be overridden in a specific service config.
The channel id can be found by visiting the channel in Salsify and copying the last section of the url.
`https://app.salsify.com/app/orgs/<org_id>/channels/<channel_id>`
To find the api key follow [this](https://developers.salsify.com/reference#token-based-authentication)
```yaml
Dynamic\Salsify\Model\Fetcher:
  apiKey: 'api key here'

Dynamic\Salsify\Model\Fetcher.example:
    channel: 'channel id'
```
or
```yaml
Dynamic\Salsify\Model\Fetcher.example:
  apiKey: 'api key here'
  channel: 'channel id'
```

An organization ID can also be included to avoid an account having access to multiple organizations.
```yaml
Dynamic\Salsify\Model\Fetcher:
  organizationID: 'org id'
```
or
```yaml
Dynamic\Salsify\Model\Fetcher.example:
  organizationID: 'org id'
```

https://developers.salsify.com/docs/organization-id

The fetcher can also have the timout changed for http requests.
This is not a timout for Salsify to generate an export.
Timeout is in milliseconds and defaults to 2000 ms or 2 seconds.
Like an `apiKey` the timeout can be set in the root fetcher config and be overridden by a service config.
```yaml
Dynamic\Salsify\Model\Fetcher:
  timeout: 4000
```
or
```yaml
Dynamic\Salsify\Model\Fetcher.example:
  timeout: 4000
```

### Mapper
To set up a mapper, which will map fields from Salsify to SilverStripe, some configuration is needed.

```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
       SKU:
        salsifyField: SKU
        unique: true
      Title: Product Title
```

Each mapper instance will need a service config.
Under the `mapping` config one or more classes can be specified for import.
Each class can have one or more fields to map, and must have at least one that is unique.
All fields have the key of the SilverStripe field to map to.
`Title: Product Title` will map `Product Title` from Salsify to `Title` in SilverStripe.

#### Unique Fields
Like non-unique fields, the key is the SilverStripe field to map to.
`salsifyField` is the field from Salsify to map.
`unique` is either true or false and will be used as a filter to check against existing records.
```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      SKU:
        salsifyField: SKU
        unique: true
```
The unique fields will be added to an array, with the values for each product and will be used as a filter to find existing.
This allows for multiple compound unique fields.

#### Field Types
Field types can be `Raw`, `Literal`, `File`, `Image`, `HasOne`, `HasMany`.
By default the `Raw` type is used. More types can also be added.

##### Literal
To set a field that doesn't have a salsify field a literal field can be used.
```yaml
Dynamic\Salsify\Model\Mapper.example:
  example:
    mapping:
      \Page:
        Author:
          value: 'Chris P. Bacon'
          type: Literal
```
The above example will set the Author field to `Chris P. Bacon` for all mapped pages.

##### Files and Images
To get an image or file from salsify to map to an object a type needs to be specified.

```yaml
Dynamic\Salsify\Model\Mapper.example:
  example:
    mapping:
      \Page:
        FrontImage:
          salsifyField: Front Image
          type: Image
```

Images and files can also be mapped by ID.
```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      FrontImageID:
        salsifyField: Front Image
        type: Image
```

If the mapping is specified as an image and it is not a valid image extension,
salsify will be used to try and convert the file into a png.

##### HasOne and HasMany
has_one and has_many relations can be done just about the same.
The `HasOne`'s `salsifyField` doesn't matter.
The `Many` type requires a salsify field that is an array.
All modifications to the data will be passed through to the mapping realtion.
```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      Document:
        salsifyField: 'salsify:id'
        type: 'HasOne'
        relation:
          \Documentobject:
            Title: Document Title
```

#### Field Fallback
A fallback field can be specified for salsify.
The fallback will be used when the normal `salsifyField` is not in the data from salsify for the object being mapped.
The fallback can be a string or array.
```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      Title:
        salsifyField: 'Product Web Title'
        fallback: 'Product Title'
```
or
```yaml
Dynamic\Salsify\Model\Mapper.example:
  mapping:
    \Page:
      Title:
        salsifyField: 'Product Web Title'
        fallback:
          - 'Product Title'
          - 'SKU'
```

#### Extending afterObjectWrite
To publish an object after mapping the `afterObjectWrite` method can be extended.
It is passed the DataObject that was written, if the object was in the database, and if the object was published.
If the object does not have the versioned extension applied `$wasPublished` will be false.

```yaml
Dynamic\Salsify\Model\Mapper.example:
  extensions:
    - ExamplePublishExtension
```

```php
<?php

use SilverStripe\Core\Extension;

/**
 * Class TestModification
 */
class ExamplePublishExtension extends Extension
{
    /**
     * This will publish all new mapped objects and mapped objects that are already published.
     * @param DataObject|Versioned $object
     * @param bool $wasWritten
     * @param bool $wasPublished
     */
    public function afterObjectWrite($object, $wasWritten, $wasPublished)
    {
        if ($object->hasExtension(Versioned::class)) {
            if (!$wasWritten || $wasPublished) {
                $object->publishRecursive();
            }
        }
    }
}
```

#### Advanced
##### [Custom Field Types](docs/en/custom-types.md)
##### [Skipping Objects](docs/en/mapper-skip-object.md)
##### [Modify Field Data](docs/en/mapper-field-modifier.md)

### Single Object Import
Adding a re-fetch button in the cms requires some configuration.
An organization is required to fetch a single product.
A `SalsifyID` field is also required for single object imports.
Only a mapping service with the name of `single` is required and will act just like a normal mapper config; however, a fetcher service config can also be defined to specify the organization.

```yaml
Dynamic\Salsify\Model\Fetcher.single:
  organizationID: 'org id'

Dynamic\Salsify\Model\Mapper.single:
  mapping:
    \Page:
      SalsifyID:
        salsifyField: 'salsify:id'
        unique: true
      SKU: SKU
      Title: GTIN Name
      FrontImage:
        salsifyField: Front Image
        type: Image
```

To use the single object mapper as a normal importer, when running the task, an `Importer` service.
```yaml
SilverStripe\Core\Injector\Injector:
  Dynamic\Salsify\Model\Importer.single:
    class: Dynamic\Salsify\Model\Importer
    constructor:
      importerKey: single
```

## Maintainers
 * Dynamic <dev@dynamicagency.com>

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
