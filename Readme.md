[![CircleCI](https://circleci.com/gh/t3n/graphql.svg?style=svg)](https://circleci.com/gh/t3n/graphql) [![Latest Stable Version](https://poser.pugx.org/t3n/graphql/v/stable)](https://packagist.org/packages/t3n/graphql) [![Total Downloads](https://poser.pugx.org/t3n/graphql/downloads)](https://packagist.org/packages/t3n/graphql)

# t3n.GraphQL

Flow Package to add graphql APIs to [Neos and Flow](https://neos.io) that also supports advanced features like schema stitching, validation rules, schema directives and more.
This package doesn't provide a GraphQL client to test your API. We suggest to use the [GraphlQL Playground](https://github.com/prisma/graphql-playground)

Simply install the package via composer:

```bash
composer require "t3n/graphql"
```

Version 2.x supports neos/flow >= 6.0.0

## Configuration

In order to use your GraphQL API endpoint some configuration is necessary.

### Endpoints

Let's assume that the API should be accessible under the URL http://localhost/api/my-endpoint.

To make this possible, you first have to add the route to your `Routes.yaml`:

```yaml
- name: 'GraphQL API'
  uriPattern: 'api/<GraphQLSubroutes>'
  subRoutes:
    'GraphQLSubroutes':
      package: 't3n.GraphQL'
      variables:
        'endpoint': 'my-endpoint'
```

Don't forget to load your routes at all:

```yaml
Neos:
  Flow:
    mvc:
      routes:
        'Your.Package':
          position: 'start'
```

Now the route is activated and available.

### Schema

The next step is to define a schema that can be queried.

Create a `schema.graphql` file:

/Your.Package/Resources/Private/GraphQL/schema.root.graphql

```graphql schema
type Query {
  ping: String!
}

type Mutation {
  pong: String!
}

schema {
  query: Query
  mutation: Mutation
}
```

Under the hood we use [t3n/graphql-tools](https://github.com/t3n/graphql-tools). This package is a php port from
[Apollos graphql-tools](https://github.com/apollographql/graphql-tools/). This enables you to use some advanced
features like schema stitching. So it's possible to configure multiple schemas per endpoint. All schemas
will be merged internally together to a single schema.

Add a schema to your endpoint like this:

```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint': # use your endpoint variable here
        schemas:
          root: # use any key you like here
            typeDefs: 'resource://Your.Package/Private/GraphQL/schema.root.graphql'
```

To add another schema just add a new entry below the `schemas` index for your endpoint.

You can also use the extend feature:

/Your.Package/Resources/Private/GraphQL/schema.yeah.graphql

```graphql schema
extend type Query {
  yippie: String!
}
```

```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint': #
        schemas:
          yeah:
            typeDefs: 'resource://Your.Package/Private/GraphQL/schema.yeah.graphql'
```

### Resolver

Now you need to add some Resolver. You can add a Resolver for each of your types.
Given this schema:

```graphql schema
type Query {
  product(id: ID!): Product
  products: [Product]
}

type Product {
  id: ID!
  name: String!
  price: Float!
}
```

You might want to configure Resolver for both types:

```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint':
        schemas:
          mySchema:
            resolvers:
              Query: 'Your\Package\GraphQL\Resolver\QueryResolver'
              Product: 'Your\Package\GraphQL\Resolver\ProductResolver'
```

Each resolver must implement `t3n\GraphQL\ResolverInterface` !

You can also add resolvers dynamically so you don't have to configure each resolver separately:

```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint':
        schemas:
          mySchema:
            resolverPathPattern: 'Your\Package\GraphQL\Resolver\Type\{Type}Resolver'
            resolvers:
              Query: 'Your\Package\GraphQL\Resolver\QueryResolver'
```

With this configuration the class `Your\Package\GraphQL\Resolver\Type\ProductResolver` would be responsible
for queries on a Product type. The {Type} will evaluate to your type name.

#### Resolver Implementation

A implementation for our example could look like this (pseudocode):

```php
<?php

namespace Your\Package\GraphQL\Resolver;

use Neos\Flow\Annotations as Flow;
use t3n\GraphQL\ResolverInterface;

class QueryResolver implements ResolverInterface
{
    protected $someServiceToFetchProducts;

    public function products($_, $variables): array
    {
        // return an array with products
        return $this->someServiceToFetchProducts->findAll();
    }

    public function product($_, $variables): ?Product
    {
        $id = $variables['id'];
        return $this->someServiceToFetchProducts->getProductById($id);
    }
}
```

```php
<?php

namespace Your\Package\GraphQL\Resolver\Type;

use Neos\Flow\Annotations as Flow;
use t3n\GraphQL\ResolverInterface;

class ProductResolver implements ResolverInterface
{
    public function name(Product $product): array
    {
        // this is just an overload example
        return $product->getName();
    }
}
```

An example query like:

```graphql
query {
  products {
    id
    name
    price
  }
}
```

would invoke the QueryResolver in first place and call the `products()` method. This method
returns an array with Product objects. For each of the objects the ProductResolver is used.
To fetch the actual value there is a DefaultFieldResolver. If you do not configure a method
named as the requests property it will be used to fetch the value. The DefaultFieldResolver
will try to fetch the data itself via `ObjectAccess::getProperty($source, $fieldName)`.
So if your Product Object has a `getName()` it will be used. You can still overload the
implementation just like in the example.

All resolver methods share the same signature:

```php
method($source, $args, $context, $info)
```

### Context

The third argument in your Resolver method signature is the Context.
By Default it's set to `t3n\GraphQContext` which exposes the current request.

It's easy to set your very own Context per endpoint. This might be handy to share some Code or Objects
between all your Resolver implementations. Make sure to extend `t3n\GraphQContext`

Let's say we have an graphql endpoint for a shopping basket (simplified):

```graphql schema
type Query {
  basket: Basket
}

type Mutation {
  addItem(item: BasketItem): Basket
}

type Basket {
  items: [BasketItems]
  amount: Float!
}

type BasketItem {
  id: ID!
  name: String!
  price: Float!
}

input BasketItemInput {
  name: String!
  price: Float!
}
```

First of all configure your context for your shopping endpoint:

```yaml
t3n:
  GraphQL:
    endpoints:
      'shop':
        context: 'Your\Package\GraphQL\ShoppingBasketContext'
        schemas:
          basket:
            typeDefs: 'resource://Your.Package/Private/GraphQL/schema.graphql'
            resolverPathPattern: 'Your\Package\GraphQL\Resolver\Type\{Type}Resolver'
            resolvers:
              Query: 'Your\Package\GraphQL\Resolver\QueryResolver'
              Mutation: 'Your\Package\GraphQL\Resolver\MutationResolver'
```

A context for this scenario would inject the current basket (probably flow session scoped);

```php
<?php

declare(strict_types=1);

namespace Your\Package\GraphQL;

use Neos\Flow\Annotations as Flow;
use Your\Package\Shop\Basket;
use t3n\GraphQL\Context as BaseContext;

class ShoppingBasketContext extends BaseContext
{
    /**
     * @Flow\Inject
     *
     * @var Basket
     */
    protected $basket;

    public function getBasket()
    {
        return $this->basket;
    }
}
```

And the corresponding resolver classes:

```php
<?php

namespace Your\Package\GraphQL\Resolver;

use Neos\Flow\Annotations as Flow;
use t3n\GraphQL\ResolverInterface;

class QueryResolver implements ResolverInterface
{
    protected $someServiceToFetchProducts;

    // Note the resolver method signature. The context is available as third param
    public function basket($_, $variables, ShoppingBasketContext $context): array
    {
        return $context->getBasket();
    }
}
```

```php
<?php

namespace Your\Package\GraphQL\Resolver;

use Neos\Flow\Annotations as Flow;
use t3n\GraphQL\ResolverInterface;

class MutationResolver implements ResolverInterface
{
    protected $someServiceToFetchProducts;

    public function addItem($_, $variables, ShoppingBasketContext $context): Basket
    {
        // construct your item with the input (simplified, don't forget validation etc.)
        $item = new BasketItem();
        $item->setName($variables['name']);
        $item->setPrice($variables['price']);

        $basket = $context->getBasket();
        $basket->addItem($item);

        return $basket;
    }
}
```

### Log incoming requests

You can enable logging of incoming requests per endpoint:

```yaml
t3n:
  GraphQL:
    endpoints:
      'your-endpoint':
        logRequests: true
```

Once activated all incoming requests will be logged to `Data/Logs/GraphQLRequests.log`. Each log entry
will contain the endpoint, query and variables.

### Secure your endpoint

To secure your api endpoints you have several options. The easiest way is to just configure
some privilege for your Resolver:

```yaml
privilegeTargets:
  'Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilege':
    'Your.Package:Queries':
      matcher: 'method(public Your\Package\GraphQL\Resolver\QueryResolver->.*())'
    'Your.Package:Mutations':
      matcher: 'method(public Your\Package\GraphQL\Resolver\MutationResolver->.*())'

roles:
  'Your.Package:SomeRole':
    privileges:
      - privilegeTarget: 'Your.Package:Queries'
        permission: GRANT
      - privilegeTarget: 'Your.Package:Mutations'
        permission: GRANT
```

You could also use a custom Context to access the current logged in user.

### Schema directives

By default this package provides three directives:

- AuthDirective
- CachedDirective
- CostDirective

To enable those Directives add this configuration to your endpoint:

```yaml
t3n:
  GraphQL:
    endpoints:
      'your-endpoint':
        schemas:
          root: # use any key you like here
            typeDefs: 'resource://t3n.GraphQL/Private/GraphQL/schema.root.graphql'
        schemaDirectives:
          auth: 't3n\GraphQL\Directive\AuthDirective'
          cached: 't3n\GraphQL\Directive\CachedDirective'
          cost: 't3n\GraphQL\Directive\CostDirective'
```

#### AuthDirective

The AuthDirective will check the security context for current authenticated roles.
This enables you to protect objects or fields to user with given roles.

Use it like this to allow Editors to update a product but restrict the removal to Admins only:

```graphql schema
type Mutation {
    updateProduct(): Product @auth(required: "Neos.Neos:Editor")
    removeProduct(): Boolean @auth(required: "Neos.Neos:Administrator")
}
```

#### CachedDirective

Caching is always a thing. Some queries might be expensive to resolve and it's worthy to cache the result.
Therefore you should use the CachedDirective:

```graphql schema
type Query {
  getProduct(id: ID!): Product @cached(maxAge: 100, tags: ["some-tag", "another-tag"])
}
```

The CachedDirective will use a flow cache `t3n_GraphQL_Resolve` as a backend. The directive accepts a maxAge
argument as well as tags. Check the flow documentation about caching to learn about them!
The cache entry identifier will respect all arguments (id in this example) as well as the query path.

#### CostDirective

The CostDirective will add a complexity function to your fields and objects which is used by some validation rules.
Each type and children has a default complexity of 1.
It allows you to annotate cost values and multipliers just like this:

```graphql schema
type Product @cost(complexity: 5) {
  name: String! @cost(complexity: 3)
  price: Float!
}

type Query {
  products(limit: Int!): [Product!]! @cost(multipliers: ["limit"])
}
```

If you query `produts(limit: 3) { name, price }` the query would have a cost of:

9 per product (5 for the product itself and 3 for fetching the name and 1 for the price (default complexity)) multiplied with 3
cause we defined the limit value as an multiplier. So the query would have a total complexity of 27.

### Validation rules

There are several Validation rules you can enable per endpoint. The most common are the QueryDepth as well as the QueryComplexity
rule. Configure your endpoint to enable those rules:

```yaml
t3n:
  GraphQL:
    endpoints:
      'some-endpoint':
        validationRules:
          depth:
            className: 'GraphQL\Validator\Rules\QueryDepth'
            arguments:
              maxDepth: 11
          complexity:
            className: 'GraphQL\Validator\Rules\QueryComplexity'
            arguments:
              maxQueryComplexity: 1000
```

The `maxQueryComplexitiy` is calculated via the CostDirective.
