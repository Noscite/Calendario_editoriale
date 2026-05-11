import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import CampaignForm from '../components/CampaignForm';
import CampaignAttachmentsManager from '../components/CampaignAttachmentsManager';
import McpServersManager from '../components/McpServersManager';
import { campaigns as campaignsApi, brands as brandsApi } from '../services/api';

export default function EditCampaignPage() {
  const { id } = useParams();
  const [campaign, setCampaign] = useState(null);
  const [brands, setBrands] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      campaignsApi.get(id).then(res => setCampaign(res.data)),
      brandsApi.list().then(res => setBrands(res.data?.data || res.data || [])),
    ]).finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="p-6 text-gray-500">Caricamento...</div>;
  if (!campaign) return <div className="p-6 text-red-600">Campagna non trovata.</div>;

  return (
    <div>
      <CampaignForm initial={campaign} brands={brands} />
      <div className="max-w-4xl mx-auto px-4 pb-8">
        <CampaignAttachmentsManager campaignId={campaign.id} />
        <McpServersManager
          scope="campaign"
          listFn={() => campaignsApi.mcpServers.list(campaign.id)}
          createFn={(payload) => campaignsApi.mcpServers.create(campaign.id, payload)}
          deleteFn={(mcpId) => campaignsApi.mcpServers.delete(campaign.id, mcpId)}
          showOverrideToggle={true}
        />
      </div>
    </div>
  );
}
