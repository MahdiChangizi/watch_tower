# !/bin/zsh

go install -v github.com/projectdiscovery/dnsx/cmd/dnsx@latest
go install -v github.com/projectdiscovery/httpx/cmd/httpx@latest
go install -v github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest
go install -v github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest
go install -v github.com/projectdiscovery/chaos-client/cmd/chaos@latest


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
source ~/.zshrc


echo 'export PDCP_API_KEY="75880f7a-04b5-4c87-9a70-a979cc8d5d2b"' >> ~/.zshrc
source ~/.zshrc

echo export PATH=$PATH:$HOME/go/bin >> $HOME/.zshrc
source $HOME/.zshrc
