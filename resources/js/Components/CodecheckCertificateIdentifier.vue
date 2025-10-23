<template>
    <div class="certificate-identifier-section">
        <div class="certificate-identifier-input-wrapper">
            <input
                type="text"
                v-model="identifier"
                placeholder="ID - e.g.: 2025-001"
                class="certificate-identifier-input"
                readonly
            />
            <select
                v-model="venueType"
                class="certificate-identifier-venue-types"
            >
                <option disabled value="default">Venue Type</option>
                <option v-for="type in venueTypes" :key="type" :value="type">
                {{ type }}
                </option>
            </select>
            <select
                v-model="venueName"
                class="certificate-identifier-venue-names"
            >
                <option disabled value="default">Venue Name</option>
                <option v-for="name in venueNames" :key="name" :value="name">
                {{ name }}
                </option>
            </select>
        </div>

        <div v-if="issueUrl" class="certificate-identifier-link-wrapper">
            <a :href="issueUrl" target="_blank">
                View the newly created GitHub Issue for this Certificate Identifier
            </a>
        </div>

        <div class="certificate-identifier-button-wrapper">
            <button
                type="button"
                class="certificate-identifier-button bg-blue"
                @click="reserveIdentifier"
            >
                Reserve Identifier
            </button>
            <button
                type="button"
                class="certificate-identifier-button bg-red"
                @click="removeIdentifier"
            >
                Remove Identifier
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
    name: String,
    value: String,
});

const emit = defineEmits(['update']);

const identifier = ref(props.value || '');
const venueType = ref('default');
const venueName = ref('default');
const venueTypes = ref([]);
const venueNames = ref([]);
const issueUrl = ref('');

onMounted(() => {
    getVenueData();
});

watch(identifier, (val) => {
    emit('update', val);
});

async function getVenueData() {
    let codecheckApiUrl = pkp.context.apiBaseUrl.replace(/\/api\/v1\/?$/, '');
    codecheckApiUrl += '/codecheck_api';

    try {
        const response = await fetch(`${codecheckApiUrl}/getVenueData`, {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json',
            'X-Csrf-Token': pkp.currentUser.csrfToken,
            },
        });
        const data = await response.json();

        if (data.success) {
            console.log('Success:', data.message);
            venueTypes.value = data.venueTypes;
            venueNames.value = data.venueNames;
        } else {
            console.error('Error:', data.error);
        }
    } catch (error) {
        console.error('Failed to fetch venue data:', error);
    }
}

async function reserveIdentifier() {
    if (venueType.value === 'default' || venueName.value === 'default') {
        alert('Please select both a Venue Type and a Venue Name.');
        return;
    }

    let codecheckApiUrl = pkp.context.apiBaseUrl.replace(/\/api\/v1\/?$/, '');
    codecheckApiUrl += '/codecheck_api';

    try {
        const response = await fetch(`${codecheckApiUrl}/reserveIdentifier`, {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json',
            'X-Csrf-Token': pkp.currentUser.csrfToken,
            },
            body: JSON.stringify({
            venueType: venueType.value,
            venueName: venueName.value,
            }),
        });
        const data = await response.json();

        if (data.success) {
            identifier.value = data.identifier;
            issueUrl.value = data.issueUrl;
            alert(`Added new issue with identifier: ${data.identifier}`);
            console.log('Added new issue with identifier: ', data.identifier, data.issueUrl);
        } else {
            console.error('Error:', data.error);
        }
    } catch (error) {
        console.error('Request failed:', error);
    }
}

function removeIdentifier() {
    identifier.value = '';
    issueUrl.value = '';
}
</script>

<style scoped>
a {
    word-break: break-all;
}

.certificate-identifier-input-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.certificate-identifier-input {
    flex: 1;
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
}

.certificate-identifier-venue-types {
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
    background: #fff;
}

.certificate-identifier-venue-names {
    font-size:14px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    height: 2.5rem;
    background: #fff;
}

.certificate-identifier-button-wrapper {
    padding-top: 0.5rem;
}

.certificate-identifier-button {
    font-size:.875rem;
    color: white;
    line-height:1.25rem;
    border: none;
    padding: .4375rem .75rem;
    border-radius: 4px;
    margin-right: 0.5rem;
    font-weight: 600;
}

.bg-blue {
    background: #006798;
}

.bg-red {
    background: #dc3545;
}
</style>