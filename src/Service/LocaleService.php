<?php

namespace App\Service;

use App\Entity\BaseMedia;
use App\Entity\Episode\Episode;
use App\Entity\Locale\EpisodeLocale;
use App\Entity\Movie;
use App\Entity\Show;
use App\Repository\Locale\BaseLocaleRepository;
use App\Repository\Locale\EpisodeLocaleRepository;
use Tmdb\Model\Image;
use Tmdb\Model\Movie as TmdbMovie;
use Tmdb\Model\Tv as TmdbShow;

class LocaleService
{
    /** @var BaseLocaleRepository */
    protected $localeRepo;

    /** @var EpisodeLocaleRepository */
    protected $episodeLocaleRepo;

    /** @var array */
    private $extractLocales;

    /**
     * LocaleService constructor.
     *
     * @param BaseLocaleRepository $localeRepo
     * @param EpisodeLocaleRepository $episodeLocaleRepo
     * @param array $extractLocales
     */
    public function __construct(
        BaseLocaleRepository $localeRepo,
        EpisodeLocaleRepository $episodeLocaleRepo,
        array $extractLocales
    ) {
        $this->localeRepo = $localeRepo;
        $this->episodeLocaleRepo = $episodeLocaleRepo;
        $this->extractLocales = $extractLocales;
    }

    /**
     * @param BaseMedia $media
     * @param TmdbShow|TmdbMovie $info
     */
    public function fillMedia(BaseMedia $media, $info): void
    {
        foreach ($this->extractLocales as $locale)
        {
            $mediaLocale = $this->localeRepo->findOrCreateByMediaAndLocale($media, $locale);

            foreach ($info->getTranslations() as $translation) {
                if ($translation->getIso6391() !== $locale)
                    continue;
                $data = $translation->getData();
                $mediaLocale->setTitle($data['title'] ?? $data['name'] ?? '');
                $mediaLocale->setSynopsis($data['overview'] ?? '');
            }

            $posterRate = 0;
            $fanartRate = 0;
            foreach ($info->getImages() as $image) {
                if ($image->getIso6391() !== $locale)
                    continue;
                /** @var Image $image */
                if ($image instanceof Image\PosterImage && $image->getVoteAverage() >= $posterRate) {
                    $mediaLocale->getImages()->setPoster(MediaService::IMAGE_BASE . $image->getFilePath());
                    $mediaLocale->getImages()->setBanner(MediaService::IMAGE_BASE . $image->getFilePath());
                    $posterRate = $image->getVoteAverage();
                }
                if ($image instanceof Image\BackdropImage && $image->getVoteAverage() >= $fanartRate) {
                    $mediaLocale->getImages()->setFanart(MediaService::IMAGE_BASE . $image->getFilePath());
                    $fanartRate = $image->getVoteAverage();
                }
            }
        }
    }

    public function needFillEpisode(Episode $episode): bool
    {
        foreach ($this->extractLocales as $locale)
        {
            if (!$this->episodeLocaleRepo->findByEpisodeAndLocale($episode, $locale)) {
                return true;
            }
        }

        return false;
    }

    public function fillEpisode(Episode $episode, $translations): void
    {
        foreach ($this->extractLocales as $locale)
        {
            $object = $this->episodeLocaleRepo->findByEpisodeAndLocale($episode, $locale);
            if (!$object) {
                $object = new EpisodeLocale();
                $object->setEpisode($episode);
                $object->setLocale($locale);
                $this->episodeLocaleRepo->persist($object);
            }

            foreach ($translations as $translation) {
                if ($translation['iso_639_1'] == $locale) {
                    $object->setTitle($translation['data']['name'] ?? '');
                    $object->setOverview($translation['data']['overview'] ?? '');
                }
            }
        }
    }
}
