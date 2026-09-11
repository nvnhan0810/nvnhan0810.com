import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdDigestRun, RdInterestScore, RdProfile } from "../../../types";

type Props = RootProps & {
  profile: RdProfile;
  interestScores: RdInterestScore[];
};

const ProfilePage = ({ auth, profile, interestScores }: Props) => {
  const route = useRoute();

  const reset = () => router.post(route("admin.reading-digest.profile.reset"));

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-100">Reading Profile</h1>
        <Button variant="outline" onClick={reset}>Reset profile</Button>
      </div>
      <h2 className="text-lg font-semibold text-gray-200 mb-2">Interest scores</h2>
      <ul className="text-sm text-gray-300 space-y-1 mb-6">
        {interestScores.map((row) => (
          <li key={row.id}>
            {row.taxonomy_node?.path ?? "unknown"}: {row.score.toFixed(1)}
          </li>
        ))}
        {interestScores.length === 0 && <li>No behavioral scores yet.</li>}
      </ul>
      <h2 className="text-lg font-semibold text-gray-200 mb-2">Explicit preferences</h2>
      <pre className="text-xs bg-muted p-4 rounded-md overflow-auto text-gray-300">
        {JSON.stringify(profile.preferences, null, 2)}
      </pre>
    </PrivateLayout>
  );
};

export default ProfilePage;
