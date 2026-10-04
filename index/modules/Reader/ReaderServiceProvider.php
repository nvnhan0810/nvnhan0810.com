<?php

declare(strict_types=1);

namespace Modules\Reader;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Reader\Application\Command\AddDocumentToCollection;
use Modules\Reader\Application\Command\CreateCollection;
use Modules\Reader\Application\Command\CreateDocument;
use Modules\Reader\Application\Command\DeleteCollection;
use Modules\Reader\Application\Command\DeleteDocument;
use Modules\Reader\Application\Command\EmptyTrash;
use Modules\Reader\Application\Command\ExchangeSsoCode;
use Modules\Reader\Application\Command\Logout;
use Modules\Reader\Application\Command\PurgeDocument;
use Modules\Reader\Application\Command\PurgeExpiredTrash;
use Modules\Reader\Application\Command\PutPageAnnotation;
use Modules\Reader\Application\Command\PutReadingProgress;
use Modules\Reader\Application\Command\RemoveDocumentFromCollection;
use Modules\Reader\Application\Command\RenameCollection;
use Modules\Reader\Application\Command\RestoreDocument;
use Modules\Reader\Application\Command\SetDocumentFavorite;
use Modules\Reader\Application\Command\UpdateDocument;
use Modules\Reader\Application\Command\UploadDocumentFile;
use Modules\Reader\Application\Command\UploadThumbnail;
use Modules\Reader\Application\Handler\AddDocumentToCollectionHandler;
use Modules\Reader\Application\Handler\CreateCollectionHandler;
use Modules\Reader\Application\Handler\CreateDocumentHandler;
use Modules\Reader\Application\Handler\DeleteCollectionHandler;
use Modules\Reader\Application\Handler\DeleteDocumentHandler;
use Modules\Reader\Application\Handler\EmptyTrashHandler;
use Modules\Reader\Application\Handler\ExchangeSsoCodeHandler;
use Modules\Reader\Application\Handler\GetCollectionHandler;
use Modules\Reader\Application\Handler\GetDocumentFileHandler;
use Modules\Reader\Application\Handler\GetDocumentHandler;
use Modules\Reader\Application\Handler\GetMeHandler;
use Modules\Reader\Application\Handler\GetPageAnnotationHandler;
use Modules\Reader\Application\Handler\GetReadingProgressHandler;
use Modules\Reader\Application\Handler\GetSyncChangesHandler;
use Modules\Reader\Application\Handler\GetThumbnailHandler;
use Modules\Reader\Application\Handler\ListAnnotationsHandler;
use Modules\Reader\Application\Handler\ListCollectionDocumentsHandler;
use Modules\Reader\Application\Handler\ListCollectionsHandler;
use Modules\Reader\Application\Handler\ListDocumentsHandler;
use Modules\Reader\Application\Handler\ListFavoritesHandler;
use Modules\Reader\Application\Handler\ListTrashHandler;
use Modules\Reader\Application\Handler\LogoutHandler;
use Modules\Reader\Application\Handler\PurgeDocumentHandler;
use Modules\Reader\Application\Handler\PurgeExpiredTrashHandler;
use Modules\Reader\Application\Handler\PutPageAnnotationHandler;
use Modules\Reader\Application\Handler\PutReadingProgressHandler;
use Modules\Reader\Application\Handler\RemoveDocumentFromCollectionHandler;
use Modules\Reader\Application\Handler\RenameCollectionHandler;
use Modules\Reader\Application\Handler\RestoreDocumentHandler;
use Modules\Reader\Application\Handler\SetDocumentFavoriteHandler;
use Modules\Reader\Application\Handler\UpdateDocumentHandler;
use Modules\Reader\Application\Handler\UploadDocumentFileHandler;
use Modules\Reader\Application\Handler\UploadThumbnailHandler;
use Modules\Reader\Application\Query\GetCollection;
use Modules\Reader\Application\Query\GetDocument;
use Modules\Reader\Application\Query\GetDocumentFile;
use Modules\Reader\Application\Query\GetMe;
use Modules\Reader\Application\Query\GetPageAnnotation;
use Modules\Reader\Application\Query\GetReadingProgress;
use Modules\Reader\Application\Query\GetSyncChanges;
use Modules\Reader\Application\Query\GetThumbnail;
use Modules\Reader\Application\Query\ListAnnotations;
use Modules\Reader\Application\Query\ListCollectionDocuments;
use Modules\Reader\Application\Query\ListCollections;
use Modules\Reader\Application\Query\ListDocuments;
use Modules\Reader\Application\Query\ListFavorites;
use Modules\Reader\Application\Query\ListTrash;
use Modules\Reader\Application\Service\DocumentHardDeleter;
use Modules\Reader\Domain\Ports\AccessTokenIssuer;
use Modules\Reader\Domain\Ports\CollectionRepository;
use Modules\Reader\Domain\Ports\DocumentRepository;
use Modules\Reader\Domain\Ports\IdpTokenClient;
use Modules\Reader\Domain\Ports\ObjectStorage;
use Modules\Reader\Domain\Ports\PageAnnotationRepository;
use Modules\Reader\Domain\Ports\ReaderUserRepository;
use Modules\Reader\Domain\Ports\ReadingProgressRepository;
use Modules\Reader\Infrastructure\Auth\SanctumAccessTokenIssuer;
use Modules\Reader\Infrastructure\Http\HttpIdpTokenClient;
use Modules\Reader\Infrastructure\Persistence\EloquentCollectionRepository;
use Modules\Reader\Infrastructure\Persistence\EloquentDocumentRepository;
use Modules\Reader\Infrastructure\Persistence\EloquentPageAnnotationRepository;
use Modules\Reader\Infrastructure\Persistence\EloquentReaderUserRepository;
use Modules\Reader\Infrastructure\Persistence\EloquentReadingProgressRepository;
use Modules\Reader\Infrastructure\Storage\SeaweedObjectStorage;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;

final class ReaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/reader.php'), 'reader');

        $this->app->bind(ReaderUserRepository::class, EloquentReaderUserRepository::class);
        $this->app->bind(DocumentRepository::class, EloquentDocumentRepository::class);
        $this->app->bind(CollectionRepository::class, EloquentCollectionRepository::class);
        $this->app->bind(PageAnnotationRepository::class, EloquentPageAnnotationRepository::class);
        $this->app->bind(ReadingProgressRepository::class, EloquentReadingProgressRepository::class);
        $this->app->bind(ObjectStorage::class, SeaweedObjectStorage::class);
        $this->app->bind(IdpTokenClient::class, HttpIdpTokenClient::class);
        $this->app->bind(AccessTokenIssuer::class, SanctumAccessTokenIssuer::class);
        $this->app->singleton(DocumentHardDeleter::class);

        $this->callAfterResolving(CommandBus::class, function (CommandBus $bus): void {
            if (! $bus instanceof LaravelCommandBus) {
                return;
            }

            $bus->register(ExchangeSsoCode::class, ExchangeSsoCodeHandler::class);
            $bus->register(Logout::class, LogoutHandler::class);
            $bus->register(CreateDocument::class, CreateDocumentHandler::class);
            $bus->register(UpdateDocument::class, UpdateDocumentHandler::class);
            $bus->register(DeleteDocument::class, DeleteDocumentHandler::class);
            $bus->register(UploadDocumentFile::class, UploadDocumentFileHandler::class);
            $bus->register(UploadThumbnail::class, UploadThumbnailHandler::class);
            $bus->register(PutPageAnnotation::class, PutPageAnnotationHandler::class);
            $bus->register(PutReadingProgress::class, PutReadingProgressHandler::class);
            $bus->register(SetDocumentFavorite::class, SetDocumentFavoriteHandler::class);
            $bus->register(RestoreDocument::class, RestoreDocumentHandler::class);
            $bus->register(PurgeDocument::class, PurgeDocumentHandler::class);
            $bus->register(EmptyTrash::class, EmptyTrashHandler::class);
            $bus->register(PurgeExpiredTrash::class, PurgeExpiredTrashHandler::class);
            $bus->register(CreateCollection::class, CreateCollectionHandler::class);
            $bus->register(RenameCollection::class, RenameCollectionHandler::class);
            $bus->register(DeleteCollection::class, DeleteCollectionHandler::class);
            $bus->register(AddDocumentToCollection::class, AddDocumentToCollectionHandler::class);
            $bus->register(RemoveDocumentFromCollection::class, RemoveDocumentFromCollectionHandler::class);
        });

        $this->callAfterResolving(QueryBus::class, function (QueryBus $bus): void {
            if (! $bus instanceof LaravelQueryBus) {
                return;
            }

            $bus->register(GetMe::class, GetMeHandler::class);
            $bus->register(ListDocuments::class, ListDocumentsHandler::class);
            $bus->register(ListFavorites::class, ListFavoritesHandler::class);
            $bus->register(ListTrash::class, ListTrashHandler::class);
            $bus->register(ListCollections::class, ListCollectionsHandler::class);
            $bus->register(GetCollection::class, GetCollectionHandler::class);
            $bus->register(ListCollectionDocuments::class, ListCollectionDocumentsHandler::class);
            $bus->register(GetDocument::class, GetDocumentHandler::class);
            $bus->register(GetDocumentFile::class, GetDocumentFileHandler::class);
            $bus->register(GetThumbnail::class, GetThumbnailHandler::class);
            $bus->register(ListAnnotations::class, ListAnnotationsHandler::class);
            $bus->register(GetPageAnnotation::class, GetPageAnnotationHandler::class);
            $bus->register(GetReadingProgress::class, GetReadingProgressHandler::class);
            $bus->register(GetSyncChanges::class, GetSyncChangesHandler::class);
        });
    }

    public function boot(): void
    {
        Route::middleware('api')->group(base_path('modules/Reader/routes/api.php'));
    }
}
