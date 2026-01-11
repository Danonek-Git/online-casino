<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\ArticleCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ArticleController extends AbstractController
{
    #[Route('/articles', name: 'articles_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('articles/index.html.twig', [
            'articles' => ArticleCatalog::all(),
        ]);
    }

    #[Route('/articles/{slug}', name: 'articles_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        foreach (ArticleCatalog::all() as $article) {
            if ($article['slug'] === $slug) {
                return $this->render($article['template'], [
                    'article' => $article,
                ]);
            }
        }

        throw $this->createNotFoundException('Artykul nie istnieje.');
    }
}
