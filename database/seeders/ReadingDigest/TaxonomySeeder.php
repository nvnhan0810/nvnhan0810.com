<?php

namespace Database\Seeders\ReadingDigest;

use App\Domains\ReadingDigest\Infrastructure\Persistence\Eloquent\TaxonomyNodeModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        if (TaxonomyNodeModel::query()->exists()) {
            return;
        }

        $tree = [
            'Programming' => [
                'Frontend' => ['React', 'TypeScript', 'CSS', 'Vue'],
                'Backend' => ['Go', 'Laravel', 'Node.js', 'Rust'],
                'DevOps' => ['Docker', 'Kubernetes', 'AWS', 'Proxmox'],
            ],
            'AI' => ['LLM', 'RAG', 'MCP', 'Agents', 'Prompt Engineering'],
            'Career' => [],
            'Startup' => [],
            'Security' => [],
        ];

        $this->seedNode($tree);
    }

    private function seedNode(array $nodes, ?string $parentId = null, ?string $parentPath = null): void
    {
        foreach ($nodes as $key => $children) {
            if (is_int($key)) {
                $label = $children;
                $slug = Str::slug($label, '.');
                $path = $parentPath ? "{$parentPath}.{$slug}" : $slug;
                $this->createNode($label, $slug, $parentId, $path);

                continue;
            }

            $label = $key;
            $slug = Str::slug($label, '.');
            $path = $parentPath ? "{$parentPath}.{$slug}" : $slug;
            $node = $this->createNode($label, $slug, $parentId, $path);

            if (is_array($children) && $children !== []) {
                $this->seedNode($children, $node->id, $path);
            }
        }
    }

    private function createNode(string $label, string $slug, ?string $parentId, string $path): TaxonomyNodeModel
    {
        return TaxonomyNodeModel::create([
            'label' => $label,
            'slug' => $slug,
            'parent_id' => $parentId,
            'path' => strtolower($path),
        ]);
    }
}
