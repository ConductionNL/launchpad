
# Quick Start Guide - Strapi Developer Docs
Source: https://docs.strapi.io/cms/quick-start

# Quick Start Guide

Strapi offers a lot of flexibility. Whether you want to go fast and quickly see the final result, or would rather dive deeper into the product, we got you covered. For this tutorial, we'll go for the DIY approach and build a project and content structure from scratch, then deploy your project to Strapi Cloud to add data from there.

*Estimated completion time: 5-10 minutes*

:::strapi Hosted demo and LaunchPad
Strapi offers a [hosted demo](https://strapi.io/demo) so you can quickly try its Content Manager and learn how to edit content. In this hosted demo, Strapi runs in production mode, so the Content-Type Builder is [disabled by design](/cms/faq#why-cant-i-create-or-update-content-types-in-productionstaging).

If you also want to try the Content-Type Builder and learn how to build a content structure, install the [LaunchPad](https://github.com/strapi/launchpad) application locally.
:::

:::prerequisites

2. The terminal will prompt you to log in or sign up. Once you do, a 30-day trial of the 

    </Tabs>

3. Answer questions in the terminal, giving your project a name (you can press Enter to keep the default name), choosing the recommended NodeJS version, and selecting the region closer to your current place:

    ![Strapi Cloud terminal questions and answers](/img/assets/quick-start-guide/qsg-strapi-cloud-terminal-questions.png)

Within a few moments, your local project will be hosted on Strapi Cloud. 🚀 

Once it's done, the terminal will provide you a clickable link that starts with `https://cloud.strapi.io/projects`. Click on the link, or copy and paste it in your browser address bar, to visit the page.

You will see the Strapi Cloud project we've just created, `my-strapi-project`, visible in the Strapi Cloud dashboard. Click the **Visit app** button in the top right corner to access your deployed Strapi project.

:::callout  Congratulations!
Now your project is hosted on Strapi Cloud and accessible online. You can learn more about Strapi Cloud by reading [its dedicated documentation](/cloud/intro) or proceed to part D to log in into your online Strapi project and add your first data from there.
:::

:::tip
Feel free to play with the Content-Type Builder even further and add more fields to your content-types or create new content-types. Anytime you make such changes, deploy them again on Strapi Cloud, by running the appropriate `deploy` command, and see your hosted project updated within a few minutes. Magical, isn't it? 🪄
:::

##  Part D: Add content to your Strapi Cloud project with the Content Manager

Now that we have created a basic content structure with 2 collection types, "Restaurant" and "Category", and deployed your project to Strapi Cloud, let's use the Cloud to actually add content by creating new entries.

<details>
<summary>Step 1: Log in to the admin panel of your new Strapi Cloud project</summary>

### Step 1: Log in to the admin panel of your new Strapi Cloud project

Now that your Strapi Cloud project is created, let's log in into the project:

1. From your , click the `my-strapi-project` project.
3. Click the **Visit app** button.
4. In the new page that opens, complete the form to create the first administrator user of this Strapi Cloud project.

Logged in into our first Strapi Cloud project, we will now add data from there.

<details>
<summary> Additional information and tips about users and Strapi Cloud projects:</summary>

:::note Note: Local users and Strapi Cloud users are different
The databases for your Strapi Cloud project and your local project are different. This means that data is not automatically transferred from your local project to Strapi Cloud. This includes users that you previously created locally. That's why you are invited to create a new administrator account when logging in to your Strapi Cloud project for the first time.
:::

:::tip Tip: Directly accessing the admin panel of your Strapi Cloud project
Any project hosted on Strapi Cloud is accessible from its own URL, something like `https://my-strapi-project-name.strapiapp.com`. To access the admin panel of your online project, simply add `/admin` to the URL, for instance as in `https://my-strapi-project-name.strapiapp.com/admin`. URLs can be found in your Strapi Cloud dashboard and you can also directly access your Strapi Cloud projects from there by clicking on the name of your project then on the **Visit app** button.
:::

</details>

</details>

<details>
<summary>Step 2: Create an entry for the "Restaurant" collection type</summary>

### Step 2: Create an entry for the "Restaurant" collection type

1. Go to  _Content Manager > Collection types - Restaurant_ in the navigation.
2. Click on **Create new entry**.
3. Type the name of your favorite local restaurant in the _Name_ field. Let's say it's `Biscotte Restaurant`.
4. In the _Description_ field, write a few words about it. If you're lacking some inspiration, you can use `Welcome to Biscotte restaurant! Restaurant Biscotte offers a cuisine based on fresh, quality products, often local, organic when possible, and always produced by passionate producers.`
5. Click **Save**.

The restaurant is now listed in the _Collection types - Restaurant_ view of the  _Content Manager_.

</details>

<details>
<summary>Step 3: Add Categories</summary>

#### Step 3: Add Categories

Let's go to  _Content Manager > Collection types - Category_ and create 2 categories:

1. Click on **Create new entry**.
2. Type `French Food` in the _Name_ field.
3. Click **Save**.
4. Go back to _Collection types - Category_, then click again on **Create new entry**.  
5. Type `Brunch` in the _Name_ field, then click **Save**.

The "French Food" and "Brunch" categories are now listed in the _Collection types - Category_ view of the  _Content Manager_.

Now, we will add a category to a restaurant:

1. Go to  _Content Manager > Collection types - Restaurant_ in the navigation, and click on "Biscotte Restaurant".
2. In the **Categories** drop-down list at the bottom of the page, select "French Food". Scroll back to the top of the page and click **Save**.

</details>

<details>
<summary>Step 4: Set Roles & Permissions</summary>

### Step 4: Set Roles & Permissions

We have just added a restaurant and 2 categories. We now have enough content to consume (pun intended). But first, we need to make sure that the content is publicly accessible through the API:

1. Click on _ Settings_ at the bottom of the main navigation.
2. Under _Users & Permissions Plugin_, choose _Roles_.
3. Click the **Public** role.
4. Scroll down under _Permissions_.
5. In the _Permissions_ tab, find _Restaurant_ and click on it.
6. Click the checkboxes next to **find** and **findOne**.
7. Repeat with _Category_: click the checkboxes next to **find** and **findOne**.
8. Finally, click **Save**.

</details>

<details>
<summary>Step 5: Publish the content</summary>

### Step 5: Publish the content

By default, any content you create is saved as a draft. Let's publish our categories and restaurant.

First, navigate to  _Content Manager > Collection types - Category_. From there:

1. Click the "Brunch" entry.
2. On the next screen, click **Publish**.
3. In the _Confirmation_ window, click **Yes, publish**.  

Then, go back to the Categories list and repeat for the "French Food" category.

Finally, to publish your favorite restaurant, go to  _Content Manager > Collection types - Restaurant_, click the "Biscotte Restaurant" entry, and **Publish** it.

</details>

<details>
<summary>Step 6: Use the API</summary>

### Step 6: Use the API

OK dear gourmet, we have just finished creating our content and making it accessible through the API. You can give yourself a pat on the back — but you have yet to see the final result of your hard work.

There you are: the list of restaurants should be accessible by visting the `/api/restaurants` path of your Strapi Cloud project URL (e.g., `https://beautiful-first-strapi-project.strapiapp.com/api/restaurants`).

Try it now! The result should be similar to the example response below 👇.

<details>
<summary>Click me to view an example of API response:</summary>

```json
{
  "data": [
    {
      "id": 3,
      "documentId": "wf7m1n3g8g22yr5k50hsryhk",
      "Name": "Biscotte Restaurant",
      "Description": [
        {
          "type": "paragraph",
          "children": [
            {
              "type": "text",
              "text": "Welcome to Biscotte restaurant! Restaurant Biscotte offers a cuisine based on fresh, quality products, often local, organic when possible, and always produced by passionate producers."
            }
          ]
        }
      ],
      "createdAt": "2024-09-10T12:49:32.350Z",
      "updatedAt": "2024-09-10T13:14:18.275Z",
      "publishedAt": "2024-09-10T13:14:18.280Z",
      "locale": null
    }
  ],
  "meta": {
    "pagination": {
      "page": 1,
      "pageSize": 25,
      "pageCount": 1,
      "total": 1
    }
  }
}
```

</details>

</details>

:::callout  Congratulations!
Now your content is created, published, and you have permissions to request it through the API.
Keep on creating amazing content!
:::

:::tip Tip: Transfer data between your local and Strapi Cloud projects
The databases for your Strapi Cloud project and your local project are different. This means that data is not automatically synchronized between your Strapi Cloud and local projects. You can use the [data management system](/cms/features/data-management) to transfer data between projects.
:::

##  What to do next?

Now that you know the basics of creating and publishing content with Strapi, we encourage you to explore and dig deeper into some Strapi features:

 learn how to use Strapi's [REST](/cms/api/rest) API to query the content,<br/>
 learn more about Strapi features by browsing the  **Features** category,<br/>
 learn more about Strapi Cloud projects by reading the [Cloud Documentation](/cloud/intro),<br/>
 and [customize your Strapi back end](/cms/backend-customization) and [admin panel](/cms/admin-panel-customization) for advanced use cases.<br/>


