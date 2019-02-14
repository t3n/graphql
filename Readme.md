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

- typeDefs
- resolverPathpattern
- single Resolver


### Policy yaml

#### schema directives
- useage example

#### validation rules
- useage example

#### Context
- default context
- create your own how to and how to access in your resolver
