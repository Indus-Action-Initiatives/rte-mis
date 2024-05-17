<!-- ### Location
District -  2
   eACH DISTRICT has - 2 block.
     each block has - 1 nagriya nikae and 1 Gram panchayat
       each gram panchayat has 2 habitaion
       each Nagriya Nikae has 2 ward and each ward has 2 habitations.

### Udise
There are 4 udise codes, each belong to Block 1-1 under district-1.

### User
1. Admin users.
   1 State user
   2 Distirct user 
   4 block users. 
       Block 1-1
       Block 1-2
       Block 2-1
       Block 2-2
    
2. active cmpaign users. user will be linked to thee location based on the name. -->

# RTE MIS Demo Content Module

## Overview

The RTE MIS Demo Content module is designed to create and manage demo content for the RTE Management Information System. It includes functionality to create taxonomy terms for locations, schools, and mini node for academic sessions, as well as creating and managing user accounts associated with these entities. The module also provides functionality to delete this demo content during uninstallation.

## Features

- **Create Location Taxonomy Terms**: Generates hierarchical location terms (districts, blocks, nagriya nikaes, wards, habitations, gram panchayats) with associated metadata.
- **Create UDISE Codes**: Generates unique UDISE codes for schools and creates corresponding taxonomy terms.
- **Create Academic Sessions**: Adds academic sessions with detailed timelines.
- **Create Users**: Automatically creates user accounts for various administrative roles (state, district, block) and associates them with location details.
- **Delete Demo Content**: Removes all created demo content (taxonomy terms, users, paragraphs, mini nodes) during module uninstallation.

## Installation

1. Clone or download the module into your Drupal site's `modules/custom` directory.
2. Enable the module using the Drupal admin UI or Drush:
    ```sh
    drush en rte_mis_demo_content
    ```

## Usage

Upon installation, the module will automatically execute the following:

### Number of Data Created

#### Location Taxonomy Terms

- **Districts**: 2
- **Blocks per District**: 2 (Total Blocks: 4)
- **Nagriyas per Block**: 1 (Total Nagriyas: 4)
- **Wards per Nagriya Nikae**: 2 (Total Wards: 8)
- **Habitations per Ward**: 2 (Total Habitations: 16)
- **Gram Panchayats per Block**: 1 (Total Gram Panchayats: 4)
- **Habitations per Gram Panchayat**: 2 (Total Habitations under Gram Panchayats: 8)

Total Location Terms: 42

#### UDISE Codes

- **Total Schools**: 7
  - **Pending Request**: 2 schools
    - UDISE Code: 66666666666
    - UDISE Code: 77777777777
  - **Approved**: 5 schools
    - Not Registered for the Campaign: 1 school
      - UDISE Code: 55555555555
    - Registered for the Campaign: 4 schools
      - **Submitted State**: 1 school
        - UDISE Code: 44444444444
      - **Approved by BEO but not by DEO**: 1 school
        - UDISE Code: 33333333333
      - **Verified and Mapped**: 2 schools
        - UDISE Code: 11111111111
        - UDISE Code: 22222222222

#### Academic Sessions

- **Academic Session Nodes**: 1
- **Timeline Paragraphs per Session**: 4

Total Academic Session Mini Nodes: 1
Total Timeline Paragraphs: 4

#### Users

- **State Admin**: 1
- **District Admins**: 2
- **Block Admins**: 4
- **School Users**: 4

Total Users: 11

#### School Mapping

We have mapped 2 schools, one for urban and one for rural area type.

- **Urban School (UDISE: 11111111111)**:
  - Mapping Details:
    - Habitation-1-1-1-1-2, District-1 >> Block-1-1 >> Nagriya-Nikhaye-1-1-1 >> Ward-1-1-1-1
    - Habitation-1-1-1-2-2, District-1 >> Block-1-1 >> Nagriya-Nikhaye-1-1-1 >> Ward-1-1-1-2
    - Habitation-1-2-1-1-1, District-1 >> Block-1-2 >> Nagriya-Nikhaye-1-2-1 >> Ward-1-2-1-1

- **Rural School (UDISE: 22222222222)**:
  - Mapping Details:
    - Habitation-GP-1-1-2, District-1 >> Block-1-1 >> Gram-Panchayat-1-1
    - Habitation-GP-1-2-1, District-1 >> Block-1-2 >> Gram-Panchayat-1-2
    - Habitation-GP-1-2-2, District-1 >> Block-1-2 >> Gram-Panchayat-1-2

#### Mapped Data for Students

Mapped data for students is available at the above-stated locations as described in the [School Mapping](#school-mapping) section.

- **Location**: Above Stated Locations in [School Mapping](#school-mapping)
- **Class**: 1st
- **DOB**: 5 to 6.5 years
- **Gender**: Co-educational (Boys & Girls)
