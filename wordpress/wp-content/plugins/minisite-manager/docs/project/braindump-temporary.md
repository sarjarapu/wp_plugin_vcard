Help me draft the design specs for the minisite listing feature, life cycle of minisite around versioning, drafts, published, and subscriptions with active, inactive or online/offline states. These specifications are later use for vibe coding the features at later time either on Code + Codex plugin or via Cursor. So be as much descriptive and clear as possible to help generate better code.

## My Sites Listing

Create a new page account/sites to show list of minisites managed by the logged in user. 

1) minisite_user does not manage any sites so the listing should be blank. however, they should be able create a new minisite, create, edit or delete their own DRAFT minisites but should not be able to publish the website for public view. Preview of website should be limited only to the minisite_user who created it. only the minisites with active subscription are publicly made visible for everyone. the minisite_user should be able to purchase or renew a subscription for a minisite that is not paid already.

2) minisite_member should be able do everything that minsite_user does. The minisite_member manage only the sites they have are authorized to do so. similar to the minisite_user, they should be able ot create a new minisite and manage in DRAFT mode, but once they made payment for this website, they should be able publish. For minisites for which they already paid subscription, the minisite_member should be able to create drafts, preview, publish, roll back any number of times until the subscription expiration date. the minisite_member should be able to take their minisite with active subscription - online/offline anytime. 

3) minisite_power users should be able do everything that minisite_member does. The minisite_power users manage only the sites that they were give authorization to manage. in otherwords , each minisite_power user could be authorized to manage multiple minisite_member user accounts. so ideally they should be able to filter minisite by name, ownership etc and do all the activities that minisite_member can do.

4) minisite_admin is super user who could do pretty much everything that minisite_power user can do and including assigning a minisite_power user to a specific minisite. 

## My Sites Listing additional thoughts :  

### Versioning, Draft and Publish

Each minisite regardless of the subscription status on it, should have versioning. any new version that is created is automatically in draft mode. if the minisite has active subscription, only the published version is publicly available. at any give point in time, there can be only one active published view of a minisite. publishing a draft version , first converts previously published version to unpublished mode and then converts the current draft version to published mode all as part of single transaction. As previously mentioned, only the minisite with active subscription are allowed to be published. 

Only the users who can manage the minisite be able to see the versionings of a give minisite, review the optional comments next to each version, see the status of the version - draft, published or unpublished, etc. they have an option to rollback to specific version. when performed, the a new version is automatically created with the details as in the selcted version to rollback to. 

### Public view

Only the minisites with active subscription are publicly viewable. the minisite_member has ability to temporarily take the minisite offline even if they have active subscription. So the public view of the minisite should also check for the minisite online or offline state before rendering the page. 

If the minisites in draft mode, when viewed by general public url - should show a message that "the minisite - <site-name> is currently in draft mode and not available for public view. if you are the minisite owner, please purchase subscription to make the contents visible for everyone". 

If the minisite is in offline mode, when viewed by general public url, show a message that the minisite - <site-name> is currently offline. if you are the minisite owner, please update the minisite status to be online for the contents visible for everyone.

### Owned by

I am not sure if we can use the created by / updated by fields as the  minisite_power or  minisite_admin user may be assisting the  minisite_member to build their website. So potentially each minisite might need an additional field to track the owned by account. this allows to restrict the access or transfer the ownership at later time.

### Subscription

Minisite at inception has no active subscription. When the minisite_member makes a payment, they are buying the subscription and it comes along with a subscription duration. If a minisite_member freshly purchases a subscription duration then the minisite would become active with expiration until the following date after the duration. However, if the minisite_member extends the subscription like in renewal period, then overall subscription duration would now be extended by a purchased duration time on top of existing subscription time. If the minisite_member forgot to renew their website and they have been inactive for a while, then purchasing the renewal extends the subscription for the duration based on the current date rather than the previous expiration date. 
