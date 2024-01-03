@servers(['web' => 'deployer@stg.km.perhutani.id -p2212'])

@setup
    $repository = 'git@gitlab.com:kantor-pusat/pht-portal-km-web.git';
    $releases_dir = '/home/deployer/stg/stg.km.perhutani.id/web/releases';
    $app_dir = '/home/deployer/stg/stg.km.perhutani.id/web';
    $current_dir = '/home/deployer/stg/stg.km.perhutani.id/web/current';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir .'/'. $release;
@endsetup

@story('deploy')
    backup
    clone_repository
    linking_vendor
    run_composer
    update_symlinks
    clear_cache
    npm_i
@endstory

@task('backup')
    rm -rf /home/deployer/stg/stg.km.perhutani.id/web/backups/codebase/*
    mv {{ $releases_dir }}/* /home/deployer/stg/stg.km.perhutani.id/web/backups/codebase
@endtask

@task('clone_repository')
    echo "Cloning repository"
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone -b develop --depth 1 {{ $repository }} {{ $new_release_dir }}
@endtask

@task('linking_vendor')
    echo "Linking vendor directory"
    rm -rf {{ $new_release_dir }}/vendor
    ln -nfs {{ $app_dir }}/vendor {{ $new_release_dir }}/vendor
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    /usr/bin/php8.1 /usr/local/bin/composer2 install --ignore-platform-reqs --no-scripts -q -o
@endtask

@task('update_symlinks')
    echo "Linking storage directory"
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo 'Linking current release ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo 'Linking static folder with ln -nfs | build, hot, storage, smartkph, knowledge'
    rm -rf {{ $current_dir }}/public/{build,hot,storage,smartkph,knowledge}
    ln -nfs {{ $app_dir }}/build {{ $current_dir }}/public
    ln -nfs {{ $app_dir }}/hot {{ $current_dir }}/public
    ln -nfs {{ $app_dir }}/storage {{ $current_dir }}/public
    ln -nfs {{ $app_dir }}/smartkph {{ $current_dir }}/public
    ln -nfs {{ $app_dir }}/knowledge {{ $current_dir }}/public
@endtask

@task('migrate')
    cd {{ $current_dir }}
    /usr/bin/php8.1 artisan migrate
@endtask

@task('clear_cache')
    echo "rebuild symlink & clearing cache"
    cd {{ $current_dir }}
    /usr/bin/php8.1 artisan cache:clear
    /usr/bin/php8.1 artisan route:clear
    /usr/bin/php8.1 artisan config:clear
    chmod -R ug+rwx storage bootstrap/cache
@endtask

@task('npm_i')
    echo "NPM install"
    cd {{ $new_release_dir }}
    /usr/bin/npm install

    echo "NPM build"
    cd {{ $new_release_dir }}
    /usr/bin/npm run build
@endtask
