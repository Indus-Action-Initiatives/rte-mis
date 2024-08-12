CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Features
 * Installation


INTRODUCTION
------------

This undertaking aims to construct an online Management Information System (MIS) in a web application This undertaking aims to construct an online Management Information System (MIS) in a web application format. This system will oversee the comprehensive implementation of the social welfare policy specified in Section 12(1)(c) of the Right of Children to Free and Compulsory Education Act, 2009, by any state government in India. The objective is to shape this product as a digital public asset, ensuring its open-source nature to promote widespread adoption and foster community-led development and sustained maintenance in the future.

Link: https://rtemis.indusaction.org/

FEATURES
--------
RTE - MIS consists of 9 different modules to categorise the functional capabilities of the MIS properly. These modules are:
1. App Admin Module: Manage core technology operations at all levels of CRUD operations.
2. State Module: Manage permissions and perform the necessary actions specific to the state.
3. School Module: School administration can manage school information collection and specific actions.
4. Student Module: Student information collected through registration and registration will be verified.
5. Lottery Module: Verified student applications will be allocated to specific schools.
6. Admission Module: After allotment, applications will be updated by the school based on the admission status.
7. Student Tracking Module: This module will track the academic performance of students under section 12(1)(c).
8. Reimbursement Module: School-wise reimbursement claims can be generated.
9. Grievance Module: All grievances will be handled by authorities at all levels.

INSTALLATION
------------

Before we begin with the site setup, Make sure that the following tools are
installed in your system.

 1. Lando ( https://docs.lando.dev/getting-started/installation.html )

The steps to setup the Local Instance of the site:

 1. git clone `git@github.com:Indus-Action-Initiatives/rte-mis.git` or use
    `https://github.com/Indus-Action-Initiatives/rte-mis.git` (if ssh is not setup in your system).
 2. `cd rte-mis`
 3. `lando start`
 4. `lando composer install`
 5. `lando drush si rte_mis --site-name="RTE MIS" --db-url=mysql://drupal10:drupal10@database/drupal10 --account-pass=admin -y`

NOTE: If you have to re-install the site after doing some changes in the profile then make sure that you remove the `settings.php` file before executing the site-install command mentioned above in `Step 5`.
