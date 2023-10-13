# migration-app
<h1>Migração de site estático para Wordpress</h1>

<br/>
 O site brasileiro sobre budismo acessoaoinsight.net existe há mais de 20 anos.
 Nesse tempo o site cresceu para a casa dos milhares de textos. Uma reforma no site foi proposta em 2023 com a idéia de migrar a página para wordpress.

 <br/><br/>

 Para fazer a migração seria preciso ler todos os arquivos estáticos, extrair de acordo com o html
 dados como títulos e coleção e fazer a migração para um banco de dados no formato wordpress.


 <br/>



 O processo de migração foi finalizado em outubro de 2023.
 <br/>
 <br/>


<h2>Estrutura</h2>


O escript foi criado pra ser rodado apenas uma vez por isso carece de interface mais requintada,<br>
tendo as funcionalidades acessadas através de três rotas específicas:

<ul>
    <li>/migrate-database: Lê os textos originais, extrai as informações e insere em tabelas simples no BD
    </li>

    <li>/migrate-wordpress: Utiliza os dados importados na primeira fase e importa para tabelas Wordpress
    </li>

    <li>/generate-redirects: Gera redirects para o .htaccess, redirecionando as urls antigas para os novos endereços
    </li>

</ul>


    <br/>
  <br/>

  Resultados da migração podem ser encontrados na pasta database_dump/
