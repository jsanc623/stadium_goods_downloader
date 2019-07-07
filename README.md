# Stadium Goods Downloader

## Usage

Use the main script from the CLI. It expects a URL as the first param,
either via `-u url` or `--url url` or `--url=url`, for example:

    php stadium_goods.php https://www.stadiumgoods.com/air-jordan
    
    php stadium_goods.php -u https://www.stadiumgoods.com/air-jordan
    
    php stadium_goods.php --url https://www.stadiumgoods.com/air-jordan
    
    php stadium_goods.php --url=https://www.stadiumgoods.com/air-jordan

It will download all the products (name and price) into a CSV (stadium_goods.csv), 
accounting for any pagination. Additionally, it will output the CSV to stdout.