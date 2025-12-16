// Young player market JavaScript
let userBudget = 0; // Will be set by PHP

function showBidModal(playerId, playerName, playerValue, ownerClub) {
    document.getElementById('bidPlayerId').value = playerId;
    document.getElementById('bidPlayerName').textContent = playerName;
    document.getElementById('bidPlayerOwner').textContent = 'Owned by ' + ownerClub;
    document.getElementById('bidPlayerValue').textContent = '€' + playerValue.toLocaleString();
    document.getElementById('bidAmount').value = Math.round(playerValue * 1.1); // Suggest 10% above market value
    document.getElementById('bidModal').classList.remove('hidden');
}

function hideBidModal() {
    document.getElementById('bidModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();

    // Close modal when clicking outside
    document.getElementById('bidModal').addEventListener('click', function (e) {
        if (e.target === this) {
            hideBidModal();
        }
    });

    // Validate bid amount
    document.getElementById('bidForm').addEventListener('submit', function (e) {
        const bidAmount = parseInt(document.getElementById('bidAmount').value);

        if (bidAmount > userBudget) {
            e.preventDefault();
            alert('Bid amount exceeds your available budget!');
        }
    });
});