<?php

class CML {
    const ESTIMATOR_CLUSTERER_KMEANS = 'clusterer.kmeans';

    /**
     * @return CML_Trainer
     */
    public static function trainer() {
        return new CML_Trainer();
    }

    /**
     * @return CML_Predictor
     */
    public static function predictor() {
        return new CML_Predictor();
    }

    /**
     * @param array|CCollection|iterable $data
     *
     * @return CML_DataTrain
     */
    public static function createDataTrain($data) {
        return new CML_DataTrain($data);
    }

    /**
     * @param array|CCollection|iterable $data
     *
     * @return CML_DataPredict
     */
    public static function createDataPredict($data) {
        return new CML_DataPredict($data);
    }

    /**
     * @return CML_Manager
     */
    public static function manager() {
        return CML_Manager::instance();
    }

    public static function modelRepository($path = null) {
        return static::manager()->getModelRepository($path);
    }

    /**
     * Cosine similarity (0-1, higher = more similar) between $text and each
     * of $candidates, via TF-IDF vectors (Rubix\ML\Transformers\
     * WordCountVectorizer + TfIdfTransformer + Kernels\Distance\Cosine) -
     * fits the vocabulary once across $text + $candidates together so
     * scores are comparable. Word-overlap based, not semantic: catches
     * reworded/reordered near-duplicates, misses true paraphrases that
     * share few words.
     *
     * @param mixed $topK
     * @param mixed $maxItemPerTransaction
     *
     * @return float[] similarity scores, same order/keys as $candidates
     */
    /**
     * Item-to-item collaborative filtering: kemiripan antar item dihitung
     * dari transaksi yang memuatnya bersama-sama.
     *
     * Memakai cosine (Rubix\ML\Kernels\Distance\Cosine) atas vektor
     * kemunculan tiap item pada transaksi, bukan sekadar menghitung berapa
     * kali dua item muncul bersama. Bedanya menentukan: hitungan mentah
     * membuat item terlaris tampak berkaitan dengan hampir segalanya, karena
     * ia memang ikut hampir di setiap transaksi. Cosine menormalkan terhadap
     * populernya masing-masing, sehingga yang tersisa keterkaitan yang
     * sesungguhnya.
     *
     * Transaksi kecil diberi bobot lebih besar (1/(n-1)): dua barang yang
     * dibeli berdua jauh lebih menunjukkan keterkaitan daripada dua barang
     * yang kebetulan sama-sama ada di keranjang berisi tiga puluh barang.
     *
     * Hanya pasangan yang pernah muncul bersama yang dihitung — pasangan
     * lain cosine-nya nol, jadi menghitungnya hanya membuang waktu O(n^2).
     *
     * Cosine tidak mengenal seberapa banyak bukti di balik sebuah skor: dua
     * item yang masing-masing hanya pernah muncul sekali, dan kebetulan di
     * keranjang yang sama, punya vektor identik sehingga skornya 1 — nilai
     * tertinggi yang mungkin, dari satu kejadian. $minPairSupport menyaringnya
     * dengan menuntut sejumlah keranjang sebelum sebuah pasangan dianggap.
     *
     * @param array $transactions          daftar transaksi, tiap transaksi daftar id item
     * @param int   $topK                  jumlah item terkait yang dikembalikan per item
     * @param int   $maxItemPerTransaction transaksi dengan item lebih banyak
     *                                     dari ini dilewati - pasangannya
     *                                     meledak kuadratik dan keranjang
     *                                     sebesar itu jarang belanja biasa
     * @param int   $minPairSupport        jumlah keranjang minimum yang memuat
     *                                     kedua item; 1 berarti tanpa penyaringan
     *
     * @return array id item => [id item terkait => skor 0..1], terurut menurun
     */
    public static function itemCollaborativeSimilarity(array $transactions, $topK = 20, $maxItemPerTransaction = 40, $minPairSupport = 1) {
        $vectors = [];
        $pairs = [];

        foreach ($transactions as $index => $itemIds) {
            $itemIds = array_values(array_unique(array_filter($itemIds)));
            $size = count($itemIds);
            if ($size < 2 || $size > $maxItemPerTransaction) {
                continue;
            }

            $weight = 1 / ($size - 1);
            foreach ($itemIds as $itemId) {
                $vectors[$itemId][$index] = $weight;
            }
            for ($i = 0; $i < $size; $i++) {
                for ($j = $i + 1; $j < $size; $j++) {
                    $left = $itemIds[$i];
                    $right = $itemIds[$j];
                    //dihitung, bukan ditandai: jumlah keranjang yang memuat
                    //keduanya itulah bukti di balik skornya
                    $support = isset($pairs[$left][$right]) ? $pairs[$left][$right] + 1 : 1;
                    $pairs[$left][$right] = $support;
                    $pairs[$right][$left] = $support;
                }
            }
        }

        if (count($vectors) == 0) {
            return [];
        }

        $cosine = new Rubix\ML\Kernels\Distance\Cosine();
        $result = [];

        foreach ($pairs as $itemId => $candidates) {
            $scores = [];
            foreach ($candidates as $otherId => $pairSupport) {
                if ($pairSupport < $minPairSupport) {
                    continue;
                }
                //Rubix menuntut kedua vektor sepanjang dan seurut sama, jadi
                //vektor jarang di atas dipadatkan hanya pada transaksi yang
                //disentuh salah satu dari keduanya - hasilnya sama dengan
                //memadatkan pada seluruh transaksi, tanpa biayanya.
                $keys = $vectors[$itemId] + $vectors[$otherId];
                $a = [];
                $b = [];
                foreach (array_keys($keys) as $key) {
                    $a[] = carr::get($vectors[$itemId], $key, 0);
                    $b[] = carr::get($vectors[$otherId], $key, 0);
                }

                $similarity = 1 - $cosine->compute($a, $b);
                if ($similarity > 0) {
                    $scores[$otherId] = $similarity;
                }
            }

            if (count($scores) == 0) {
                continue;
            }
            arsort($scores);
            $result[$itemId] = array_slice($scores, 0, $topK, true);
        }

        return $result;
    }

    /**
     * Association rule mining (Apriori) atas keranjang transaksi.
     *
     * Menjawab pertanyaan yang berbeda dari itemCollaborativeSimilarity():
     * bukan "produk apa yang mirip dengan A" melainkan "kalau seseorang
     * membeli A dan B, apa lagi yang biasanya ikut" — bentuk yang berguna
     * untuk paket bundling dan promo, dan yang hasilnya dapat dibaca manusia.
     *
     * Tiga ukuran dikembalikan sekaligus karena masing-masing sendirian
     * menyesatkan:
     *
     * - **support** — seberapa sering kombinasinya muncul. Besar berarti
     *   layak digarap, tetapi tidak berarti berkaitan.
     * - **confidence** — dari yang membeli antecedent, berapa bagian yang
     *   ikut membeli consequent. Terlihat meyakinkan, tetapi tinggi dengan
     *   sendirinya untuk barang yang memang laris — hampir semua orang
     *   membelinya, apa pun isi keranjang lainnya.
     * - **lift** — confidence dibagi support consequent. Inilah yang
     *   memisahkan keterkaitan sejati dari sekadar populer: lift 1 berarti
     *   tidak berhubungan, di atas 1 berarti kehadiran antecedent
     *   benar-benar menaikkan peluang consequent.
     *
     * Apriori memangkas dengan sifat anti-monoton: sebuah kombinasi tidak
     * mungkin sering muncul kalau bagiannya saja jarang. Tanpa itu jumlah
     * kombinasi yang harus dihitung meledak kombinatorial.
     *
     * @param array $transactions   daftar transaksi, tiap transaksi daftar id item
     * @param float $minSupport     ambang kemunculan, sebagai pecahan dari
     *                              jumlah transaksi (0.01 = muncul di 1% transaksi)
     * @param float $minConfidence  ambang confidence sebuah aturan
     * @param int   $maxItemsetSize batas ukuran kombinasi; tiap tingkat menaikkan
     *                              biayanya, dan aturan beranggota banyak jarang
     *                              dapat ditindaklanjuti
     *
     * @return array daftar aturan {antecedent[], consequent, support, confidence, lift},
     *               terurut menurun menurut lift
     */
    public static function associationRules(
        array $transactions,
        $minSupport = 0.01,
        $minConfidence = 0.3,
        $maxItemsetSize = 3
    ) {
        $baskets = [];
        foreach ($transactions as $itemIds) {
            $itemIds = array_values(array_unique(array_filter($itemIds)));
            if (count($itemIds) > 0) {
                sort($itemIds);
                $baskets[] = $itemIds;
            }
        }

        $total = count($baskets);
        if ($total == 0) {
            return [];
        }

        $minCount = max(1, (int) ceil($minSupport * $total));
        $frequent = [];

        //Tingkat 1: item tunggal.
        $counts = [];
        foreach ($baskets as $itemIds) {
            foreach ($itemIds as $itemId) {
                $counts[$itemId] = carr::get($counts, $itemId, 0) + 1;
            }
        }
        $level = [];
        foreach ($counts as $itemId => $count) {
            if ($count >= $minCount) {
                $level[] = [$itemId];
                $frequent[(string) $itemId] = $count;
            }
        }

        //Tingkat berikutnya: gabungkan yang sering muncul, buang yang bagiannya
        //sendiri sudah jarang, baru hitung ke transaksi.
        for ($size = 2; $size <= $maxItemsetSize && count($level) > 0; $size++) {
            $candidates = static::joinItemsets($level, $frequent);
            if (count($candidates) == 0) {
                break;
            }

            $counts = [];
            foreach ($baskets as $itemIds) {
                if (count($itemIds) < $size) {
                    continue;
                }
                $lookup = array_flip($itemIds);
                foreach ($candidates as $key => $candidate) {
                    $found = true;
                    foreach ($candidate as $itemId) {
                        if (!isset($lookup[$itemId])) {
                            $found = false;

                            break;
                        }
                    }
                    if ($found) {
                        $counts[$key] = carr::get($counts, $key, 0) + 1;
                    }
                }
            }

            $level = [];
            foreach ($counts as $key => $count) {
                if ($count >= $minCount) {
                    $level[] = $candidates[$key];
                    $frequent[$key] = $count;
                }
            }
        }

        return static::buildRules($frequent, $total, $minConfidence);
    }

    /**
     * Kandidat kombinasi berikutnya, sudah dipangkas.
     *
     * @param array $level    kombinasi sering-muncul berukuran k
     * @param array $frequent kunci kombinasi => jumlah kemunculan
     *
     * @return array kunci => kombinasi berukuran k+1
     */
    protected static function joinItemsets(array $level, array $frequent) {
        $candidates = [];
        $count = count($level);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $merged = array_values(array_unique(array_merge($level[$i], $level[$j])));
                if (count($merged) != count($level[$i]) + 1) {
                    continue;
                }
                sort($merged);
                $key = implode(',', $merged);
                if (isset($candidates[$key])) {
                    continue;
                }

                //Sifat anti-monoton: kalau ada bagiannya yang sudah jarang,
                //gabungannya mustahil sering — dibuang sebelum menyentuh data.
                $keep = true;
                foreach ($merged as $itemId) {
                    $subset = array_values(array_diff($merged, [$itemId]));
                    if (!isset($frequent[implode(',', $subset)])) {
                        $keep = false;

                        break;
                    }
                }
                if ($keep) {
                    $candidates[$key] = $merged;
                }
            }
        }

        return $candidates;
    }

    /**
     * Menurunkan aturan dari kombinasi yang sering muncul.
     *
     * Hanya consequent tunggal — "kalau beli A dan B, beli juga C" dapat
     * ditindaklanjuti, sedangkan "beli juga C dan D" jarang berguna dan
     * melipatgandakan jumlah aturannya.
     *
     * @param array $frequent      kunci kombinasi => jumlah kemunculan
     * @param int   $total         jumlah transaksi
     * @param float $minConfidence
     *
     * @return array
     */
    protected static function buildRules(array $frequent, $total, $minConfidence) {
        $rules = [];

        foreach ($frequent as $key => $count) {
            $itemset = explode(',', $key);
            if (count($itemset) < 2) {
                continue;
            }

            foreach ($itemset as $consequent) {
                $antecedent = array_values(array_diff($itemset, [$consequent]));
                $antecedentKey = implode(',', $antecedent);
                $antecedentCount = carr::get($frequent, $antecedentKey);
                $consequentCount = carr::get($frequent, (string) $consequent);
                if (!$antecedentCount || !$consequentCount) {
                    continue;
                }

                $confidence = $count / $antecedentCount;
                if ($confidence < $minConfidence) {
                    continue;
                }

                $rules[] = [
                    'antecedent' => array_map('intval', $antecedent),
                    'consequent' => (int) $consequent,
                    'support' => $count / $total,
                    'confidence' => $confidence,
                    'lift' => $confidence / ($consequentCount / $total),
                ];
            }
        }

        usort($rules, function ($a, $b) {
            return $b['lift'] <=> $a['lift'];
        });

        return $rules;
    }

    /**
     * Cosine similarity (0-1, higher = more similar) between $text and each
     * of $candidates, via TF-IDF vectors (word-overlap based, not semantic -
     * catches reworded/reordered near-duplicates, misses true paraphrases
     * that share few words). Case-insensitive: both $text and every
     * candidate are lowercased before vectorizing.
     *
     * @param string $text
     * @param array<int|string, string> $candidates
     *
     * @return float[] same keys as $candidates
     */
    public static function textSimilarity($text, array $candidates) {
        if (empty($candidates)) {
            return [];
        }

        $keys = array_keys($candidates);
        // WordCountVectorizer's default tokenizer (Rubix\ML\Tokenizers\Word)
        // does not normalize case, so "Distributor" and "distributor" count
        // as unrelated words and silently zero out the cosine score instead
        // of raising an error - lowercase here so natural-language inputs
        // compare on word identity, not byte identity.
        $samples = array_map(function ($value) {
            return [mb_strtolower((string) $value)];
        }, array_merge([$text], array_values($candidates)));

        $dataset = Rubix\ML\Datasets\Unlabeled::build($samples);
        $vectorizer = new Rubix\ML\Transformers\WordCountVectorizer();
        $vectorizer->fit($dataset);
        $dataset->apply($vectorizer);
        $tfidf = new Rubix\ML\Transformers\TfIdfTransformer();
        $tfidf->fit($dataset);
        $dataset->apply($tfidf);

        $vectors = $dataset->samples();
        $needle = $vectors[0];
        $cosine = new Rubix\ML\Kernels\Distance\Cosine();

        $scores = [];
        foreach ($keys as $i => $key) {
            $scores[$key] = 1 - $cosine->compute($needle, $vectors[$i + 1]);
        }

        return $scores;
    }
}
