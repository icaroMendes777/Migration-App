# migration-app

<h1>Migração de site estático para Wordpress</h1>

<br/>
 Este é um script que foi criado para migrar um site antigo para wordpress. O site original já tinha 20 anos e carregava muito código legado. A maioria do material estava em arquivos estáticos.
<br/>

Para fazer a migração foi preciso ler todos os arquivos, extrair as informações como título e categoria
e fazer a migração para um banco de dados no formato wordpress.
<br/>

Para manter o seo do site antigo, também foi necessário gerar um arquivo com htaccess redirects das antigas para as novas urls.

 <h2>Setup</h2>

Instale as dependências

    composer install

Crie um arquivo .env com base no arquivo .env.example no root do projeto.

Gere a chave da aplicação:

    php artisan key:generate

Insira a conexão com o banco de uma aplicação Wordpress Mysql. Execute as migrações para criar as tabelas necessárias no processo:

    php artisan migrate

Observação: como a execução pode levar algum tempo, garanta que o tempo de timeout no php seja alto.

<h2>Execução</h2>

O script foi criado pra ser rodado apenas uma vez por isso carece de interface mais requintada<br>
Para realiza a migração acesse as rotas na seguite ordem:

<ul>
<li>
/migrate-database: Lê os textos originais, extrai as informações e insere em tabelas simples no BD
</li>

<li>
/migrate-wordpress: Utiliza os dados importados na primeira fase e importa para tabelas Wordpress
</li>

<li>
/generate-redirects: Gera redirects para o .htaccess, redirecionando as urls antigas para os novos endereços
</li>

</ul>

<br/>
<br/>

Resultados da migração podem ser encontrados na pasta database_dump/
