# Excavator
===
Excavator is a simple PHP script that downloads archived data from httparchive.org for a website. You can download .har file & .csv archive file. You can use those files to analyze the behavior of your site in terms of performance or just to see whether the site has improved over the time or its performace degraded.

## Download & Installation
---
It has pretty straight forward downlod and installation guide.

~~~
# install composer if you have not already
> curl -sS https://getcomposer.org/installer | php
> git clone https://github.com/bhargav2785/excavator.git
> cd excavator
> composer install
~~~

## Usage
---
Its very simple, just invoke with php.

~~~
> php excavator.php [options]
~~~

## Options
---
The script has two mendatory options and one optional option.

**`-s`**, required

The url of the website

**`-d`**, required

Where do you want to store it? Provide a path on your system.

**`--dry`**, optional

If this option is set, the script will run normally except it won't actually download files.


## Overview
---
Sometimes it is possible that for a given search keyword it finds multiple websites. For example if your url is http://www.yahoo.com then it finds more than one websites that matched the term as shown below.

1. `http://www.yahoo.com/`
2. `http://www.yahoo.com.cn/`
3. `http://www.yahoo.com.tw/`
4. `http://www.yahoo.com.au/`
5. `http://www.yahoo.com.br/`

In this case, you can select the number that you are interested. Say for you example you want archived data for http://www.yahoo.com/tw, then you would go ahead and enter `3`. That will download the data for Yahoo Taiwan site. Or if you want to download data for all, you'd simply enter `all` and hit enter. 


## Changelog
---
** `1.0` ** `2015.01.30` Initial version


## License
---
[WTF](http://www.wtfpl.net/) license
