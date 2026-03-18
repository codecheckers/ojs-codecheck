# CODECHECK Plugin Test Dataset

## Apply the CODECHECK Test Dataset to an OJS instance

- Download [OJS 3.5](https://pkp.sfu.ca/software/ojs/download/) and install it
- Clone the [PKP Datasets Repository](https://github.com/pkp/datasets)
    - Place it somewhere on your local machine — there is no need for it to be in the direcrtory of the beforehand installed OJS instance
- Copy the directory [stable-3_5_0-codecheck](stable-3_5_0-codecheck) into `/datasets/ojs` of the PKP Datasets Repository
- `cd /ojs` in your OJS installation directory
    - `mkdir files`
    - Set environment variables (adjust credentials to match your MySQL setup):
    ```bash
    export DBTYPE=MySQL
    export DBTYPE_SYMBOLIC=mysql
    export DBUSERNAME=root
    export DBPASSWORD=root
    export DBNAME=ojs_dump
    export DBHOST=localhost
    export APP=ojs
    export BRANCH=stable-3_5_0-codecheck
    ```
    - `/path/to/datasets/tools/load.sh`
- Update `config.inc.php` database settings:
    ```
    driver = mysqli
    host = localhost
    username = root
    password = root
    name = ojs_dump
    ```
- `php -S localhost:8000` (Username: `admin`, Password: `admin`)

---

## Dataset Contents

**Journal:** CODECHECK Demo Journal (path: `codecheck`)

**Users:** All passwords follow the pattern `username` repeated twice (except `admin`)

| Username | Password | Role |
|---|---|---|
| admin | admin | Administrator |
| jmanager | jmanager | Journal Manager, Editor |
| seglen | seglen | Author |
| dnuest | dnuest | Author |
| fostermann | fostermann | Author |
| rreviewer | rreviewer | Reviewer |

**Articles:** 8 submissions from the [CODECHECK register](https://codecheck.org.uk/register/)
- 5 published (3 in Issue 1, 2 in Issue 2)
- 2 under review
- 1 submitted

Demonstrates:
- Article with two codecheckers (2020-002: Eglen + Nüst)
- Three different codecheckers (Eglen, Nüst, Ostermann)
- Multiple conference venues (AGILE, GigaScience, J Geogr Syst)