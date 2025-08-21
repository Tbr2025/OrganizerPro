  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Auction | {{ $auction->name }}</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
      @keyframes bounce-text {

          0%,
          100% {
              transform: translateY(0);
          }

          50% {
              transform: translateY(-20px);
          }
      }

      .animate-bounce-text {
          animation: bounce-text 1s infinite;
      }

      body {
          background: #000;
          display: flex;
          justify-content: center;
          align-items: center;
          height: 100vh;
      }

      /* Fixed-size card container */
      .card-container {
          position: relative;
          width: 1601px;
          height: 910px;
          /* Set your card image height */
          background: url('/images/player-card.jpeg') no-repeat center center;
          background-size: auto;
          /* Keep original image size */
      }

      /* Position elements inside */
      #player-image {
          position: absolute;
          bottom: 305px;
          left: 114px;
          width: 380px;
          object-fit: cover;
      }

      #player-name {
          position: absolute;
          top: 210px;
          left: 545px;
          font-size: 46px;
          text-transform: uppercase;
      }

      #tmh {
          position: absolute;
          top: 490px;
          left: 600px;
          font-size: 33px;

          text-transform: uppercase;
      }

      #tm {
          position: absolute;
          top: 550px;
          left: 605px;
          font-size: 33px;
          color: #000;

          text-transform: uppercase;
      }

      .hidden {
          display: none;
      }

      #twh {
          position: absolute;
          top: 490px;
          left: 825px;
          font-size: 33px;
          text-transform: uppercase;
      }

      #tw {

          position: absolute;
          top: 550px;
          left: 825px;
          font-size: 33px;
          text-transform: uppercase;
          color: #000;

      }

      #trh {
          position: absolute;
          top: 490px;
          left: 1020px;
          font-size: 33px;
          text-transform: uppercase;
      }

      #tr {
          position: absolute;
          top: 550px;
          left: 1050px;
          font-size: 33px;
          color: #000;
          text-transform: uppercase;
      }

      #player-role {
          position: absolute;
          top: 275px;
          left: 570px;
      }

      #player-batting {
          position: absolute;
          top: 334px;
          left: 570px;
          font-size: 34px;
          font-weight: bold;
      }

      #player-bowling {
          position: absolute;
          top: 404px;
          left: 570px;
          font-size: 34px;
          font-weight: bold;
      }

      #current-bid {
          position: absolute;
          left: 234px;
          bottom: 197px;
      }

      #winning-team {
          position: absolute;
          top: 470px;
          left: 570px;
      }

      #bid-list-container {
          position: absolute;
          top: 623px;
          left: 543px;
          width: 250px;
          height: 245px;
          overflow-y: auto;
          background: rgba(0, 0, 0, 0.5);
          padding: 8px;
          border-radius: 6px;
      }

      #sold-badge {
          position: absolute;
          bottom: 27px;
          left: 112px;
          /* bottom: 200px; */
          display: flex;
          align-items: center;
          justify-content: center;
          width: 150px;
          height: 150px;
          z-index: 9;
      }

      #team-logo {
          position: absolute;
          position: absolute;
          bottom: 27px;
          left: 300px;
          width: 100px;
          height: 100px;
          object-fit: contain;
      }

      ul#bid-list {
          font-size: 25px;
      }


      #sold-text {
          bottom: 243px;
          left: 186px;
          position: absolute;
          font-size: 32px;
      }
  </style>
