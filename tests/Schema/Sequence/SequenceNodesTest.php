<?php

use Husail\EdiSdk\Schema\Sequence\Group;
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Schema\Sequence\ManyNode;
use Husail\EdiSdk\Schema\Sequence\GroupNode;
use Husail\EdiSdk\Schema\Sequence\IdentifyBy;
use Husail\EdiSdk\Schema\Sequence\RecordNode;
use Husail\EdiSdk\Schema\Sequence\AmbiguousNode;

describe('Sequence nodes', function () {

    describe('Record factory', function () {
        it('Record::one() creates a required RecordNode', function () {
            $layout = simpleRecord('header');
            $node   = Record::one($layout);

            expect($node)->toBeInstanceOf(RecordNode::class);
            expect($node->required)->toBeTrue();
            expect($node->layout->name)->toBe('header');
        });

        it('Record::optional() creates a non-required RecordNode', function () {
            $layout = simpleRecord('segment_b');
            $node   = Record::optional($layout);

            expect($node)->toBeInstanceOf(RecordNode::class);
            expect($node->required)->toBeFalse();
        });

        it('Record::many() creates a ManyNode', function () {
            $layout = simpleRecord('detail');
            $node   = Record::many($layout);

            expect($node)->toBeInstanceOf(ManyNode::class);
            expect($node->layout->name)->toBe('detail');
        });
    });

    describe('Group factory', function () {
        it('Group::repeat() creates a GroupNode with IdentifyBy', function () {
            $header  = simpleRecord('batch_header');
            $trailer = simpleRecord('batch_trailer');

            $node = Group::repeat(
                identifyBy: fn ($line) => null,
                children: [Record::one($header), Record::one($trailer)]
            );

            expect($node)->toBeInstanceOf(GroupNode::class);
            expect($node->children)->toHaveCount(2);
            expect($node->identifyBy)->toBeInstanceOf(IdentifyBy::class);
        });

        it('Group::ambiguous() creates an AmbiguousNode with IdentifyBy', function () {
            $segA = simpleRecord('segment_a');
            $segB = simpleRecord('segment_b');

            $node = Group::ambiguous(
                identifyBy: fn ($line) => null,
                children: [Record::many($segA), Record::optional($segB)]
            );

            expect($node)->toBeInstanceOf(AmbiguousNode::class);
            expect($node->children)->toHaveCount(2);
            expect($node->identifyBy)->toBeInstanceOf(IdentifyBy::class);
        });

        it('identifyBy is invokable and returns correct record name', function () {
            $node = Group::repeat(
                identifyBy: fn (string $line): ?string => match ($line[0]) {
                    'H' => 'header',
                    'T' => 'trailer',
                    default => null,
                },
                children: [Record::one(simpleRecord('header')), Record::one(simpleRecord('trailer'))]
            );

            expect(($node->identifyBy)('H0001'))->toBe('header');
            expect(($node->identifyBy)('T0001'))->toBe('trailer');
            expect(($node->identifyBy)('X0001'))->toBeNull();
        });
    });

    describe('recordNames()', function () {
        it('RecordNode returns its own record name', function () {
            $node = Record::one(simpleRecord('header'));
            expect($node->recordNames())->toBe(['header']);
        });

        it('ManyNode returns its own record name', function () {
            $node = Record::many(simpleRecord('detail'));
            expect($node->recordNames())->toBe(['detail']);
        });

        it('GroupNode returns all nested record names', function () {
            $node = Group::repeat(
                identifyBy: fn ($line) => null,
                children: [
                    Record::one(simpleRecord('batch_header')),
                    Record::many(simpleRecord('detail')),
                    Record::one(simpleRecord('batch_trailer')),
                ]
            );

            expect($node->recordNames())->toBe(['batch_header', 'detail', 'batch_trailer']);
        });

        it('AmbiguousNode returns all nested record names', function () {
            $node = Group::ambiguous(
                identifyBy: fn ($line) => null,
                children: [
                    Record::many(simpleRecord('segment_a')),
                    Record::optional(simpleRecord('segment_b')),
                ]
            );

            expect($node->recordNames())->toBe(['segment_a', 'segment_b']);
        });

        it('nested GroupNode inside GroupNode returns all names recursively', function () {
            $inner = Group::ambiguous(
                identifyBy: fn ($line) => null,
                children: [
                    Record::many(simpleRecord('segment_a')),
                    Record::optional(simpleRecord('segment_b')),
                ]
            );

            $outer = Group::repeat(
                identifyBy: fn ($line) => null,
                children: [
                    Record::one(simpleRecord('batch_header')),
                    $inner,
                    Record::one(simpleRecord('batch_trailer')),
                ]
            );

            expect($outer->recordNames())->toBe(['batch_header', 'segment_a', 'segment_b', 'batch_trailer']);
        });
    });
});
