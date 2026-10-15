<?php

use PHPStan\Type\Type;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\MixedType;
use PHPStan\Type\TypeCombinator;

/**
 * Inti porting Larastan\Larastan\ReturnTypes\AppMakeHelper, dipakai bersama
 * oleh ekstensi `c::container()` dan `CContainer_Container::make()`/
 * `makeWith()`. SENGAJA versi ringan, bukan port penuh: Larastan sungguhan
 * men-resolve binding container yang NYATA (lewat app Laravel yang di-boot
 * di proses PHPStan) supaya `app(FooInterface::class)` bisa bertipe
 * `FooConcrete` kalau memang di-bind begitu. Di CF, mem-boot ulang
 * CContainer sungguhan di tengah analisis statis berisiko memicu efek
 * samping closure binding (koneksi DB, dll.) yang ditulis orang lain -
 * jadi versi ini CUMA menangani kasus paling umum: argumen pertama berupa
 * string kelas literal (`Foo::class` / `'Foo'`), dikembalikan sebagai tipe
 * kelas itu APA ADANYA, tanpa mencoba tahu apakah ada rebinding interface.
 * Itu sudah benar untuk kelas konkret (overwhelming majority pemakaian
 * `c::container(Foo::class)` di app) dan tidak pernah salah untuk interface
 * (hasilnya tetap valid sebagai tipe interface-nya, cuma tidak menyempit ke
 * implementasi sungguhan). Resolve binding nyata dicatat sebagai pekerjaan
 * terpisah di docs/NOTES.md kalau suatu saat worth risikonya.
 *
 * @internal
 */
final class CQC_Phpstan_Service_Support_ContainerMakeHelper {
    /**
     * @param Arg[] $args
     */
    public function resolveTypeFromArgs(array $args, Scope $scope): Type {
        if (count($args) === 0) {
            return new MixedType();
        }

        $argType = $scope->getType($args[0]->value);
        $constantStrings = $argType->getConstantStrings();

        if (count($constantStrings) === 0) {
            return new MixedType();
        }

        $types = [];
        foreach ($constantStrings as $constantString) {
            if ($constantString->isClassString()->yes()) {
                $types[] = $constantString->getClassStringObjectType();

                continue;
            }

            return new MixedType();
        }

        return TypeCombinator::union(...$types);
    }
}
