[![Build Status](https://travis-ci.com/t3n/graphql.svg?branch=master)](https://travis-ci.com/t3n/graphql)

# t3n.GraphQL
Flow Package to add graphql APIs to Neos and Flow that also supports advanced features like schema stitching, validation rules, schema directives and more.
This package doesn't provide a GraphQL client to test you API. We suggest to use the [GraphlQL Playground](https://github.com/prisma/graphql-playground)

## Setup
Simply install the package via composer:

```bash
composer require "t3n/graphql"
```

## Configuration
In order to use your GraphQL API endpoint some configuration is necessary.

### Endpoints
Let's assume that the API should be accessible under the URL http://localhost/api/my-endpoint.

To make this possible, you first have to add the route to your `Routes.yaml`:
```yaml
    -
      name: 'GraphQL API'
      uriPattern: 'api/<GraphQLSubroutes>'
      subRoutes:
        'GraphQLSubroutes':
          package: 't3n.GraphQL'
          variables:
            'endpoint': 'my-endpoint'
```

Don't forget to load the routes at all:

```yaml
Neos:
  Flow:
    mvc:
      routes:
        'Your.Package':
          position: 'start'
```
Now the route is activated and available.

#### Schema
The next step is to define a schema that can be queried via the API.

First off create a `schema.graphql` file:
/Your.Package/Resources/Private/GraphQL/schema.root.graphql

```graphql schema
type Query {
    ping: String!
}

type Mutation {
    pong: String!
}
```

Under the hood we use [t3n/garphql-tools](https://github.com/t3n/graphql-tools). This package is a php port from 
[Apollos graphql-tools](https://github.com/apollographql/graphql-tools/). This enables you to use some advanced 
features like schema stitching for a endpoint. So it's possible to configure multiple schemas per endpoint. All schemas
will be merged internally together to a single schema.

Add your schema like this:

```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint': # use your endpoint variable here
        schemas:
          root: # use any key you like here
            typeDefs: 'resource://Your.Package/Private/GraphQL/schema.root.graphql'
```

To add another schema just add a new entry below the `schemas` index in your `Settings.yaml`

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

#### Resolver
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
        mySchema: 
          resolvers:
            Query: 'Your\Package\GraphQL\Resolver\QueryResolver'
            Product: 'Your\Package\GraphQL\Resolver\ProductResolver'
```

Each resolver must implement `t3n\GraphQL\ResolverInterface`.

You can also add resolvers dynamically so you don't have to configure each resolver separately:
```yaml
t3n:
  GraphQL:
    endpoints:
      'my-endpoint': 
        mySchema:
          resolverPathPattern: 'Your\Package\GraphQL\Resolver\Type\{Type}Resolver'
          resolvers:
            Query: 'Your\Package\GraphQL\Resolver\QueryResolver'
```
With this configuration the class `Your\Package\GraphQL\Resolver\Type\ProductResolver` would be responsible
for queries on a Product type.

##### Resolver Implementation
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
returns an array with Product Objects. For each of the Objects the ProductResolver is used.
To fetch the actual value there is a DefaultFieldResolver. If you do not configure a method 
named as the requests property it will kick in. The DefaultFieldResolver will try to fetch
the data itself via `ObjectAccess::getProperty($source, $fieldName)`. 
So if you Product Object has a `getName()` it will be used. You can still overload the
implementation just like in the example.

All resolver methods share the same signature:
```php 
method($source, $args, $context, $info)
```

#### Context
The third argument in your Resolver is the Context. By Default it's set to `t3n\GraphQContext` wich
exposes the current request.

It's easy to set your very own Context per endpoint. This might be handy to share some Code or Objects
between all your Resolver implementations.

Let's say we have an graphql endpoint for a shopping basket (simplified):
````graphql schema
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
````
First of all configure your context for your shopping endpoint:
````yaml
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

````

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

#### Policy.yaml
To secure your api endpoints you have several options. The easiest way is to just configure
some privliege for your Resolver:

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

#### schema directives
- useage example

#### validation rules
- useage example
