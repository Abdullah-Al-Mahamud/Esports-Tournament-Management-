function generateFixtures(tournamentId) {
    if (!confirm('Are you sure you want to generate fixtures for this tournament?')) {
        return false;
    }
    return true;
}

function viewFixtures(tournamentId) {
    const fixturesSection = document.getElementById('fixtures-' + tournamentId);
    if (fixturesSection) {
        fixturesSection.style.display = fixturesSection.style.display === 'none' ? 'block' : 'none';
    }
}
