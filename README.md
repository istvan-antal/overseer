# Setup

```bash
make init-dev
cd dev
./launch.py --env youblueprintenvironment
```

## Required setup

There are a few things you need to do to get these examples to work. This assumes you're using JIRA 4.4+ or Confluence 4+.

Generate an RSA pub/priv key pair. Atlassian's OAuth provider uses RSA-SHA1 to sign the request. For the purpose of the examples here, you can just use the rsa.pem and rsa.pub keys stored in the root directory. Do not use the keys provided here for your own application. Please generate your own keys for your own application.

Configure an Application Link. To register an OAuth consumer, you'll need to register an Application Link inside your Atlassian product. Refer to the Atlassian docs on how to do this for your product.

After you've created an Application Link, configure an "Incoming Authentication" with the following details:

 Consumer key:          dpf43f3p2l4k3l03
 Consumer name:         OAuth Test
 Description:           OAuth Test Example
 Public key:            <paste the contents of rsa.pub>
 Consumer callback URL: http://<hostname where you're hosting this code>/auth

source: https://bitbucket.org/atlassian_tutorial/atlassian-oauth-examples/src

Name the pem file overseer.pem and put it in the project's root folder.