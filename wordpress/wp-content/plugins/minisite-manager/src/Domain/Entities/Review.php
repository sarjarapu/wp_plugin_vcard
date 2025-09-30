<?php
namespace Minisite\Domain\Entities;

final class Review {

	public function __construct(
		public ?int $id,
		public int $minisiteId,
		public string $authorName,
		public ?string $authorUrl,
		public float $rating,
		public string $body,
		public ?string $locale,
		public ?string $visitedMonth,  // YYYY-MM
		public string $source,         // manual|google|yelp|facebook|other
		public ?string $sourceId,
		public string $status,         // pending|approved|rejected
		public ?\DateTimeImmutable $createdAt,
		public ?\DateTimeImmutable $updatedAt,
		public ?int $createdBy
	) {}
}
