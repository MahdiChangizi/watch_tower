# !/bin/zsh

# install zsh and oh-my-zsh
sudo apt-get install zsh -y
chsh -s $(which zsh)
sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
source ~/.zshrc

# install go and set path
curl -fsSL https://go.dev/dl/go1.23.5.linux-amd64.tar.gz | sudo tar -C /usr/local/ -xzv
echo "export PATH=$PATH:/usr/local/go/bin" >> ~/.zshrc
source ~/.zshrc


# install apache2 and php and composer and postgresql
sudo apt-get install apache2 php php-cli php-fpm php-json php-pdo php-pgsql php-zip php-gd php-mbstring php-curl php-xml php-bcmath php-intl php-sqlite3 php-imagick php-dev -y
sudo apt-get install postgresql-client -y
sudo apt-get install composer -y
sudo apt-get install postgresql -y

# config postgresql
sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD 'mahdi3276';"
sudo -u postgres psql -c "CREATE DATABASE watch;"


# install git
sudo apt-get install git -y

# install tools
go install -v github.com/projectdiscovery/dnsx/cmd/dnsx@latest
go install -v github.com/projectdiscovery/httpx/cmd/httpx@latest
go install -v github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest
go install -v github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest
go install -v github.com/projectdiscovery/chaos-client/cmd/chaos@latest
go install github.com/samogod/samoscout@latest


echo "crtsh(){
    query=$(cat <<-END
        SELECT
            ci.NAME_VALUE
        FROM
            certificate_and_identities ci
        WHERE
            plainto_tsquery('certwatch', '$1') @@ identities(ci.CERTIFICATE)
END
)
    echo "$query" | psql -t -h crt.sh -p 5432 -U guest certwatch | sed 's/ //g' | egrep ".*.\.$1" | sed 's/*\.//g' | tr '[:upper:]' '[:lower:]' | sort -u
}" >> ~/.zshrc

# alias for nuclei
echo "alias watch_nuclei='php /var/www/watch_tower/src/Nuclei/program.php'" >> ~/.zshrc

echo 'export PDCP_API_KEY="75880f7a-04b5-4c87-9a70-a979cc8d5d2b"' >> ~/.zshrc
source ~/.zshrc

echo export PATH=$PATH:$HOME/go/bin >> $HOME/.zshrc
source $HOME/.zshrc


# get access to php and apache2 files
sudo chmod -R 777 .
sudo chmod -R 777 /var/www
sudo chmod -R 777 /var/www/watch_tower
sudo chmod -R 777 /var/www/watch_tower/public
sudo chmod -R 777 /var/www/watch_tower/src
sudo chmod -R 777 /var/www/watch_tower/db
sudo chmod -R 777 /var/www/watch_tower/tools
sudo chmod -R 777 /var/www/watch_tower/src/Nuclei
sudo chmod -R 777 /var/www/watch_tower/src/Tools
sudo chmod -R 777 /var/www/watch_tower/src/Services
sudo chmod -R 777 /var/www/watch_tower/src/Enum