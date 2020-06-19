GraphQL Gateway
===============
Serves as the federated gateway for wikis using the MediaWiki [GraphQL Extension](https://www.mediawiki.org/wiki/Extension:GraphQL).

### Wikimedia
GraphQL Gateway https://graphql.toolforge.org/

#### Deployment
  1.  `ssh login.toolforge.org`
  2.  `become graphql`
  3.  `cd www/js`
  4.  `git pull origin master`
  5.  `webservice --backend=kubernetes --mem 2Gi node10 shell`
  6.  `cd www/js`
  7.  `npm ci`
  8.  `exit`
  9.  `webservice --backend kubernetes node10 stop`
  10. `webservice --backend kubernetes --canonical --cpu 1 --mem 2Gi node10 start`