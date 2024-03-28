# WebfactoryHttpCacheBundle

WebfactoryHttpCacheBundle is a Symfony bundle that features a more
powerful [HTTP cache validation via the last modified header] than the
```@Cache``` annotation in the excellent [SensioFrameworkExtraBundle].

[HTTP cache validation via the last modified header]: https://symfony.com/doc/current/http_cache/validation.html#validation-with-the-last-modified-header
[SensioFrameworkExtraBundle]: https://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/cache.html

While the SensioFrameworkExtraBundle's ```@Cache``` annotation restricts
you to the request parameters, the ```@ReplaceWithNotModifiedResponse```
annotation lets you write small LastModifiedDeterminators for each one
of the underlying ressources of the requested page, They can be reused
and combined freely and can even be defined as services.

Lets take the example from the SensioFrameworkExtraBundle docs (stripped
off the ETag part, which is not supported by the
WebfactoryHttpCacheBundle):

```php
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * @Cache(lastModified="post.getUpdatedAt()")
 */
public function indexAction(Post $post)
{
    // your code
    // won't be called in case of a 304
}
```

This falls short if the rendered template e.g. contains information
about the x latest posts. That can be done with the
```@ReplaceWithNotModifiedResponse``` annotation:

```php
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;

/**
 * @ReplaceWithNotModifiedResponse({"@app_caching_post", "@app_caching_latest_posts"})
 */
public function indexAction(Post $post)
{
    // your code
    // won't be called in case of a 304
}
```

When Symfony's routing has chosen this controller action, all of the
LastModifiedDeterminators are called to return their respective last
modified date.

In this case, both LastModifiedDeterminators are configured as services:
```@app_caching_post``` and ```@app_caching_latest_posts```. The first
one returns the update date of the requests $post, the second one may
use the PostRepository injected from the DI container to return the last
update date of the x latest posts.

Then, ReplaceWithNotModifiedResponse combines all of the
LastModifiedDeterminators dates to determine to last modified date of
the overall page. Finally, if the request contains an appropriate
```if-not-modified-since``` header, the execution of the controller
action will be skipped and an empty response with a 304 Not Modified
status code will be sent. If your LastModifiedDeterminators are fast,
this can improve your performance greatly.

What we like about the LastModifiedDeterminators is that they encourage
to separate the concerns nicely and encapsulate the tasks into small
units that are easy to understand, reusable and unit test.
   
*Note:* `@ReplaceWithNotModifiedResponse` does not alter or add
`Cache-Control` header settings. So, by default your response will
remain `private` and end up in browser caches only. If you want it to be
kept in surrogate caches (like Varnish or the Symfony Http Cache), you
can add `@Cache(smaxage="0")`. This will make the response `public`, but
also requires a revalidation on every request as the response is
*always* considered stale. [Learn more about Symonfy's HTTP caching].

[Learn more about Symonfy's HTTP caching]: http://symfony.com/doc/current/book/http_cache.html

 

## Installation

Install via [composer](https://getcomposer.org/):

    composer require webfactory/http-cache-bundle

Register the bundle in your application:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Webfactory\HttpCacheBundle\WebfactoryHttpCacheBundle(),
        // ...
    );
    // ...
}
```



## Usage

Choose a controller action you want to possibly replace with a 304 Not Modified response. Write one LastModifiedDeterminator for each
of the different underlying resources, implementing the ```Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator``` interface.

```php
<?php
// src/Caching/PostsLastModifiedDeterminator.php
namespace App\Caching;

use App\Entity\PostRepository;
use Symfony\Component\HttpFoundation\Request;
use Webfactory\HttpCacheBundle\NotModified\LastModifiedDeterminator;

/**
 * Returns the publishing date of the latest posts.
 */
final class PostsLastModifiedDeterminator implements LastModifiedDeterminator
{
    /** @var EntityRepository */
    private $postRepository;

    public function __construct(PostRepository $postRepository)
    {
        $this->postRepository = $postRepository;
    }
    
    public function getLastModified(Request $request)
    {
        $post = $this->postRepository->findLatest();
        return $post->getPublishingDate();
    }
}
```

You can use the ```$request``` in the getLastModified e.g. to get route parameters, which is necessary e.g. if you have
some filters coded in the requested URL.

If your LastModifiedDeterminator has dependencies you'd like to be injected, configure it as a service.

Then, simply add the ```ReplaceWithNotModifiedResponse``` annotation to the chosen controller method and parameterise it
with your LastModifiedDeterminators:

```php
<?php

namespace src\Controller;

use Symfony\Component\HttpFoundation\Response;
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;

final class MyController
{
    /**
     * @ReplaceWithNotModifiedResponse({...})
     */
    public function indexAction()
    {
        // ...
        return new Response(...);
    }
}
```

The most simple form of adding a LastModifiedDeterminator is passing its fully qualfified class name:

    @ReplaceWithNotModifiedResponse({"\App\Caching\MySimpleLastModifiedDeterminator"})

If your LastModifiedDeterminator needs simple constructor arguments, you can pass them in array form:

    @ReplaceWithNotModifiedResponse({ {"\App\Caching\MyLastModifiedDeterminator" = {"key1" = 1, "key2" = {"*"} } } })

This would pass the array ['key1' => 1, 'key2' => ['*']] as an argument to MyLastModifiedDeterminator's constructor.

If your LastModifiedDeterminator has more sophisticated dependencies, you can define the LastModifiedDeterminator as a service, e.g.:

```yaml
// services.yml
services:
    app_caching_latest_posts:
        class: App\Caching\PostsLastModifiedDeterminator
        arguments:
            - @repository_post
```

and note the service name to the Annotation:

    @ReplaceWithNotModifiedResponse({"@app_caching_latest_posts"})

To combine multiple LastModifiedDeterminators, simply add all of them to the annotation:
 
    @ReplaceWithNotModifiedResponse({
        "@app_caching_latest_posts",
        "\App\Caching\MySimpleLastModifiedDeterminator",
        {"\App\Caching\MyLastModifiedDeterminator" = {"key1" = 1, "key2" = {"*"}}}
    })
 
The latest last modified date determines the last modified date of the response.

## Credits, Copyright and License

This bundle was started at webfactory GmbH, Bonn.

- <https://www.webfactory.de>
- <https://twitter.com/webfactory>

Copyright 2018-2019 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).
