# WebfactoryHttpCacheBundle

WebfactoryHttpCacheBundle is a Symfony bundle that eases
[HTTP cache validation via the last modified header](https://symfony.com/doc/current/http_cache/validation.html#validation-with-the-last-modified-header).

It provides the ```ReplaceWithNotModifiedResponse``` annotation for your controller actions. This annotation can be
parameterised with Voters, one for each of the underlying ressources that overall determine the last modified date for
the response. By extracting the "last modified date of a ressource" parts into small, reusable Voters, it helps to keep
the controller clean and redundance-free. Compare for yourself a controller handling all by itself:

```php
<?php
// src/Controller/ArticleController.php
namespace App\Controller;

// ...

class ArticleController extends Controller
{
    public function show(Article $article, Request $request)
    {
        $author = $article->getAuthor();

        $articleDate = new \DateTime($article->getUpdatedAt());
        $authorDate = new \DateTime($author->getUpdatedAt());

        $date = $authorDate > $articleDate ? $authorDate : $articleDate;

        $response = new Response();
        $response->setLastModified($date);
        // Set response as public. Otherwise it will be private by default.
        $response->setPublic();

        if ($response->isNotModified($request)) {
            return $response;
        }

        // ... do more work to populate the response with the full content

        return $response;
    }
}
```

to a controller with an annotated action:

```php
<?php
// src/Controller/ArticleController.php
namespace App\Controller;

// ...
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;

class ArticleController extends Controller
{
    /**
     * @ReplaceWithNotModifiedResponse(voters = {"@app_caching_articlevoter"})
     */
    public function show(Article $article, Request $request)
    {
        // ... do work to populate the response with the full content

        return $response;
    }
}
```

And the extracted Voter:

```php
<?php
// src/Caching/ArticleVoter.php
namespace App\Caching;

use Webfactory\HttpCacheBundle\NotModified\VoterInterface;

class ArticleVoter implements VoterInterface
{
    /** @var EntityRepository */
    private $articleRepository;

    public function __construct(EntityRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }
    
    public function getLastModified(Request $request)
    {
        $article = $this->articleRepository->find($request->get('id'));
    
        $author = $article->getAuthor();

        $articleDate = new \DateTime($article->getUpdatedAt());
        $authorDate = new \DateTime($author->getUpdatedAt());

        return $authorDate > $articleDate ? $authorDate : $articleDate;
    }
}
```

With it's service definition:

```yaml
// services.yml
services:
    app_caching_articlevoter:
        class: App\Caching\ArticleVoter
        arguments:
            - @repository_article
```

Although this seems to be more code, we find it to be a good deal, as we separate the concerns better and encapsulate
the tasks in a smaller units that are easier to understand and test. Another advantage becomes obvious when we have
another controller action that depends on an article for it's last modified date - we can reuse the ArticleVoter and
combine it with other Voters.

   

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

Choose a controller action you want to possibly replace with a 304 Not Modified response. Write one Voter for each
of the different underlying resources, implementing the ```Webfactory\HttpCacheBundle\NotModified\VoterInterface```.

```php
<?php
// src/Caching/ArticlesVoter.php
namespace App\Caching;

use App\Entity\ArticleRepository;
use Webfactory\HttpCacheBundle\NotModified\VoterInterface;

/**
 * Returns the publishing date of the latest article.
 */
class ArticlesVoter implements VoterInterface
{
    /** @var EntityRepository */
    private $articleRepository;

    public function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }
    
    public function getLastModified(Request $request)
    {
        $article = $this->articleRepository->findLatest();
        return $article->getPublishingDate();
    }
}
```

You can use the ```$request``` in the getLastModified e.g. to get route parameters, which is necessary e.g. if you have
some filters coded in the requested URL.

If your Voter has dependencies you'd like to be injected, configure it as a service.

Then, simply add the ```ReplaceWithNotModifiedResponse``` annotation to the chosen controller method and parameterise it
with your Voters:

```php
<?php

namespace src\Controller;

use Symfony\Component\HttpFoundation\Response;
use Webfactory\HttpCacheBundle\NotModified\Annotation\ReplaceWithNotModifiedResponse;

final class MyController
{
    /**
     * @ReplaceWithNotModifiedResponse(voters = {...})
     */
    public function indexAction()
    {
        // ...
        return new Response(...);
    }
}
```

The most simple form of adding a Voter is passing it's fully qualfified class name:

    @ReplaceWithNotModifiedResponse(voters = {"\My\Namespace\MySimpleVoter"})

If your voter needs simple constructor arguments, you can pass them in array form:

    @ReplaceWithNotModifiedResponse(voters = { {"\My\Namespace\MyVoter" = {"key1" = 1, "key2" = {"*"} } } })

This would pass the array ['key1' => 1, 'key2' => ['*']] as an argument to MyVoter's constructor.

If your voter has more sophisticated dependencies, you can define the Voter as a service, e.g.:

```yaml
// services.yml
services:
    app_caching_articlesvoter:
        class: App\Caching\ArticlesVoter
        arguments:
            - @repository_article
```

and note the service name to the Annotation:

    @ReplaceWithNotModifiedResponse(voters = {"@app_caching_articlesvoter"})

To combine multiple Voters, simply add all of them to the voters array:
 
    @ReplaceWithNotModifiedResponse(voters = {
        "@app_caching_articlesvoter",
        "\My\Namespace\MySimpleVoter",
        {"\My\Namespace\MyVoter" = {"key1" = 1, "key2" = {"*"}}}
    })
 
The latest "last modified" date determines the last modified date of the response.



## Credits, Copyright and License

This bundle was started at webfactory GmbH, Bonn.

- <http://www.webfactory.de>
- <http://twitter.com/webfactory>

Copyright 2018 webfactory GmbH, Bonn. Code released under [the MIT license](LICENSE).
