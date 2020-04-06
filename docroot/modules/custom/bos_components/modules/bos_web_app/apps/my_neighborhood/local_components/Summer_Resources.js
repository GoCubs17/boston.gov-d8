class SummerResources extends React.Component {
  render() {
    // Content for card
    const contentPools = [
      {
        heading: this.props.bcyf_pool_center_name,
        content: this.props.bcyf_pool_center_address
      },
      {
        content: (
          <div>
            Learn more about the <a href={"/summer-boston"}>City in the summer</a>.
            <spacefill></spacefill>
          </div>
        )
      }
    ];
    const contentTotSprays = [
      {
        heading: this.props.tot_name,
        content: this.props.tot_address
      },
      {
        content: (
          <div>
            Learn more about the <a href={"/summer-boston"}>City in the summer</a>.
            <spacefill></spacefill>
          </div>
        )
      }
    ];
    const secDesc = "Cooling centers, pools and tot sprays near you.";
    const cardsSummer = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Summer Resources</h2>
        </div>
        <div className="supporting-text">
          <p>{secDesc}</p>
        </div>
        <div className="g">
          {/* Pool info */}
          <MnlCard
            title={"A Pool Near You"}
            image_header={
              configProps.pathImage+"pool.svg"
            }
            content_array={contentPools}
          />
          {/* Tot Sprays */}
          <MnlCard
            title={"A Tot Spray Near You"}
            image_header={
              configProps.pathImage+"tot_spray.svg"
            }
            content_array={contentTotSprays}
          />
        </div>
        <button className="t--upper t--sans"
          onClick={() => {
            this.props.displaySection(null);
          }}
        >
          Back to results
        </button>
      </div>
    );
    let displaySummer;
    if (this.props.section == "summer") {
      history.pushState(null, null, configProps.path+'?p3');
      displaySummer = cardsSummer;
    } else if (this.props.section == null) {
      displaySummer = (
        <div
          className="cd g--4 g--4--sl m-t500  cdp-l"
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("summer");
          }}
        >
          <MnlSection
            title={"Summer Resources"}
            image_header={
              configProps.pathImage+"sun.svg"
            }
            desc={secDesc}
          />
        </div>
      );
    } else {
      displaySummer = null;
    }
    return displaySummer;
  }
}